<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function confirmRemoval(Pet $pet): View
    {
        abort_unless(auth()->user()?->canManageRecords(), 403);
        $pet->load('owner')->loadCount(['vaccinations', 'dewormingRecords']);

        return view('staff.remove-pet', ['pet' => $pet]);
    }

    public function destroyPet(Request $request, Pet $pet): RedirectResponse
    {
        abort_unless($request->user()?->canManageRecords(), 403);
        $request->validate(['confirm_removal' => ['required', 'accepted']]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($pet): void {
            // Serialize against medical updates/reminder generation for this pet.
            Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail()->delete();
        });

        return redirect()->route('staff.pets')
            ->with('success', 'Pet removed from active records. Medical history has been preserved.');
    }

    public function editPet(Pet $pet): View
    {
        return view('staff.edit-pet', ['pet' => $pet]);
    }

    public function updatePet(\App\Http\Requests\UpdatePetRequest $request, Pet $pet): RedirectResponse
    {
        $values = $request->safe()->only(['name', 'species', 'breed', 'sex', 'date_of_birth', 'color']);
        if ($request->hasFile('photo')) {
            $values['photo_path'] = $request->file('photo')->store('pet-photos', 'public');
        }
        $pet->update($values);

        return redirect()->route('pets.show', $pet)->with('success', 'Pet details updated successfully.');
    }

    public function dashboard(): View
    {
        return view('staff.dashboard', app(\App\Services\StaffDashboardAnalytics::class)->data(auth()->user()));
    }

    public function users(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->where('role', User::ROLE_USER)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('contact_number', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('staff.users', ['users' => $users, 'search' => $search]);
    }

    public function userDetails(User $user): View
    {
        abort_unless($user->isOwner(), 404);
        $pets = Pet::query()
            ->where('owner_email', $user->email)
            ->orderBy('name')
            ->with(['vaccinations', 'dewormingRecords'])
            ->get();

        return view('staff.user-details', ['client' => $user, 'pets' => $pets]);
    }

    public function allPets(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $species = \Illuminate\Support\Str::ucfirst(mb_strtolower(trim((string) $request->query('species'))));

        $speciesList = Pet::query()
            ->selectRaw('LOWER(TRIM(species)) AS normalized_species')
            ->whereRaw("TRIM(species) <> ''")
            ->distinct()->orderBy('normalized_species')->pluck('normalized_species')
            ->map(fn ($value) => \Illuminate\Support\Str::ucfirst($value));

        $pets = Pet::query()
            ->with('vaccinations')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereLike('name', "%{$search}%")
                        ->orWhereLike('code', "%{$search}%")
                        ->orWhereLike('owner_name', "%{$search}%")
                        ->orWhereLike('owner_email', "%{$search}%");
                });
            })
            ->when($species !== '', fn($q) => $q->whereRaw('LOWER(TRIM(species)) = ?', [mb_strtolower($species)]))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.pets', [
            'pets' => $pets,
            'search' => $search,
            'speciesFilter' => $species,
            'speciesList' => $speciesList,
        ]);
    }

    public function createPet(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $clients = User::query()
            ->where('role', User::ROLE_USER)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->take(20)
            ->get();

        return view('staff.register-pet', [
            'clients' => $clients,
            'search' => $search,
            'selectedClient' => $request->integer('client') ? User::where('role', User::ROLE_USER)->findOrFail($request->integer('client')) : null,
        ]);
    }

    public function storePet(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_USER)],
            'name' => ['required', 'string', 'max:120'],
            'species' => ['required', 'string', 'max:60'],
            'breed' => ['nullable', 'string', 'max:120'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'color' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $owner = User::query()
            ->whereKey($validated['owner_id'])
            ->where('role', User::ROLE_USER)
            ->firstOrFail();

        if (mb_strlen($owner->name) > 120 || mb_strlen($owner->email) > 120) {
            throw ValidationException::withMessages(['owner_id' => 'The selected client name and email must each fit within 120 characters. Ask an administrator to update the client profile.']);
        }

        $pet = Pet::create([
            'name' => $validated['name'],
            'species' => $validated['species'],
            'breed' => $validated['breed'] ?? null,
            'sex' => $validated['sex'],
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'color' => $validated['color'] ?? null,
            'owner_name' => $owner->name,
            'owner_email' => $owner->email,
            'owner_number' => $owner->contact_number ?? '',
        ]);

        if ($request->hasFile('photo')) {
            $pet->update([
                'photo_path' => $request->file('photo')->store('pet-photos', 'public'),
            ]);
        }

        return redirect()
            ->route('staff.users.show', $owner)
            ->with('success', "{$pet->name} was registered for {$owner->name}.");
    }
}
