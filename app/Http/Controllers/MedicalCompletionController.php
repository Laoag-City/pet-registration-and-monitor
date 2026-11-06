<?php

namespace App\Http\Controllers;

use App\Models\DewormingRecord;
use App\Models\Pet;
use App\Models\Vaccination;
use App\Services\PetCareReminders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalCompletionController extends Controller
{
    public function vaccination(Request $request, Vaccination $vaccination): RedirectResponse
    {
        return $this->complete($request, $vaccination, 'vaccinations');
    }

    public function deworming(Request $request, DewormingRecord $deworming): RedirectResponse
    {
        return $this->complete($request, $deworming, 'deworming');
    }

    private function complete(Request $request, Vaccination|DewormingRecord $record, string $type): RedirectResponse
    {
        abort_unless($request->user()?->canManageRecords(), 403);
        $values = $request->validate([
            'administered_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'next_due_at' => ['nullable', 'date_format:Y-m-d', 'after:administered_at'],
            'confirm_completion' => ['required', 'accepted'],
        ]);
        DB::transaction(function () use ($record, $values, $type, $request) {
            // Same pet lock order as existing medical create/edit workflows. Recheck after locking.
            $pet = Pet::whereKey($record->pet_id)->lockForUpdate()->firstOrFail();
            $record = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $reminders = app(PetCareReminders::class);
            if ($record->schedule_completed_at || ! $record->next_due_at
                || DB::table('record_change_audits')->where('record_table', $record->getTable())->where('record_id', $record->id)->where('action', 'schedule_completed')->exists()
                || ! $reminders->latestRecords($record::class)->whereKey($record->id)->exists()) {
                throw ValidationException::withMessages(['completion' => 'This schedule is already completed, superseded, or has no due date. Refresh the records page.']);
            }
            if ($values['administered_at'] < $record->administered_at->toDateString()) {
                throw ValidationException::withMessages(['administered_at' => 'The actual administration cannot precede the previous treatment.']);
            }
            $treatment = $type === 'vaccinations' ? 'vaccine' : 'product';
            // Do not copy an old weight, clinician or notes into a newly administered treatment.
            $administration = $record->newQuery()->create([
                'pet_id' => $pet->id, $treatment => $record->$treatment,
                'administered_at' => $values['administered_at'],
                'next_due_at' => $values['next_due_at'] ?? null,
            ]);
            $record->forceFill(['schedule_completed_at' => $values['administered_at']])->save();
            app(\App\Services\RecordAudit::class)->write($record, $request->user(), 'schedule_completed', 'Staff confirmed treatment administered.', [
                'administration_id' => $administration->id, 'administered_at' => $values['administered_at'], 'next_due_at' => $values['next_due_at'] ?? null,
            ]);
            $reminders->generateForPet($pet);
        });

        return redirect()->route($type.'.index')->with('success', 'Schedule completed. A new administration record was saved; previous history is unchanged.');
    }
}
