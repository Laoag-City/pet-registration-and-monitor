<?php

namespace App\Http\Controllers;

use App\Models\DewormingRecord;
use App\Models\Pet;
use App\Http\Controllers\Concerns\MedicalRecordWorkflow;
use App\Http\Requests\MedicalRecordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DewormingController extends Controller
{
    use MedicalRecordWorkflow;

    public function index(): View
    {
        $user = auth()->user();
        $pets = Pet::query()
            ->when(! $user->canManageRecords(), fn ($q) => $q->where('owner_email', $user->email))
            ->orderBy('name')
            ->get();

        $records = DewormingRecord::query()
            ->with('pet')
            ->whereIn('pet_id', $pets->pluck('id'))
            ->latest('administered_at')
            ->get();

        $currentRecordIds = app(\App\Services\PetCareReminders::class)->latestRecords(DewormingRecord::class)
            ->whereIn('pet_id', $pets->pluck('id'))->pluck('id');

        return view('deworming', [
            'pets' => $pets,
            'records' => $records,
            'currentRecordIds' => $currentRecordIds,
            'stats' => ['total' => $records->count()] + $this->stats($records->whereIn('id', $currentRecordIds)),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->createMedicalRecord($request, 'deworming');
    }

    public function review(MedicalRecordRequest $request): View
    {
        return $this->reviewMedicalRecord($request, 'deworming');
    }

    public function store(MedicalRecordRequest $request): RedirectResponse
    {
        $values = $request->validated();
        DB::transaction(function () use ($values): void {
            $pet = $this->medicalRecordPet($values);
            Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail();
            $pet->dewormingRecords()->create(Arr::except($values, ['owner_id', 'pet_id']));
            app(\App\Services\PetCareReminders::class)->generateForPet($pet);
        });

        return redirect()->route('deworming.index')->with('success', 'Deworming record saved.');
    }

    public function edit(DewormingRecord $deworming): View
    {
        $deworming->load('pet.owner');
        abort_unless($deworming->pet, 404);

        return view('medical.form', [
            'type' => 'deworming', 'record' => $deworming,
            'pet' => $deworming->pet, 'owner' => $deworming->pet->owner,
            'values' => $deworming->attributesToArray(),
        ]);
    }

    public function update(MedicalRecordRequest $request, DewormingRecord $deworming): RedirectResponse
    {
        DB::transaction(function () use ($request, $deworming): void {
            $pet = Pet::whereKey($deworming->pet_id)->lockForUpdate()->firstOrFail();
            $deworming->update(Arr::except($request->validated(), ['owner_id', 'pet_id']));
            app(\App\Services\PetCareReminders::class)->generateForPet($pet);
        });

        return redirect()->route('deworming.index')->with('success', 'Deworming record updated successfully.');
    }

    private function stats(Collection $records): array
    {
        $today = today();
        $dueSoon = $today->copy()->addDays(30);
        $overdue = $records->filter(fn (DewormingRecord $record) => $record->next_due_at?->isBefore($today))->count();
        $upcoming = $records->filter(fn (DewormingRecord $record) => $record->next_due_at?->betweenIncluded($today, $dueSoon))->count();

        return ['total' => $records->count(), 'current' => $records->count() - $overdue - $upcoming, 'dueSoon' => $upcoming, 'overdue' => $overdue];
    }
}
