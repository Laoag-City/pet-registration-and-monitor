<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['profile' => $request->user()]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'profile' => $request->user(),
            'maximumLength' => $request->user()->pets()->withTrashed()->exists() ? 120 : 255,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $values = $request->safe()->only(['name', 'email', 'contact_number', 'date_of_birth']);

        DB::transaction(function () use ($request, $values): void {
            // The session alone determines the account; never use a submitted ID.
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->isOwner(), 403);

            if ($values['email'] !== $user->email && Pet::withTrashed()->where('owner_email', $values['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'This email is already associated with pet records. Please contact OCV staff.',
                ])->redirectTo(route('profile.edit'));
            }

            $pets = $user->pets()->withTrashed()->lockForUpdate()->get(['id']);
            // Recheck column limits if a pet was registered after request validation.
            if ($pets->isNotEmpty() && (mb_strlen($values['name']) > 120 || mb_strlen($values['email']) > 120)) {
                throw ValidationException::withMessages([
                    'name' => 'Pet owners must use a name and email of at most 120 characters.',
                ])->redirectTo(route('profile.edit'));
            }

            $user->fill($values);
            $syncPetContact = $user->isDirty(['name', 'email', 'contact_number']);
            if ($user->isDirty('email')) {
                // Verification is not currently enforced; an old verification cannot apply to a new email.
                $user->email_verified_at = null;
            }
            $user->save();

            if ($syncPetContact && $pets->isNotEmpty()) {
                Pet::withTrashed()->whereKey($pets->modelKeys())->update([
                    'owner_name' => $user->name,
                    'owner_email' => $user->email,
                    'owner_number' => $user->contact_number,
                ]);
            }
        });

        $request->user()->refresh();

        return redirect()->route('profile.show')
            ->with('success', 'Your personal details have been updated successfully.');
    }
}
