<?php

namespace App\Services;

use App\Models\{Vaccination, DewormingRecord, AnimalHealthMonitoringRecord, RoutineServiceRecord, Pet, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecordDeletion
{
    public function delete(Model $record, User $actor): void
    {
        abort_unless($actor->canManageRecords(), 403);
        abort_unless(in_array($record::class, [Vaccination::class, DewormingRecord::class, AnimalHealthMonitoringRecord::class, RoutineServiceRecord::class], true), 403);
        DB::transaction(function () use ($record, $actor) {
            $medical = $record instanceof Vaccination || $record instanceof DewormingRecord;
            $pet = $medical ? Pet::withTrashed()->whereKey($record->pet_id)->lockForUpdate()->firstOrFail() : null;
            $current = $record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $current->delete();
            DB::table('record_deletion_audits')->insert([
                'actor_id' => $actor->id, 'record_table' => $current->getTable(),
                'record_id' => $current->id, 'action' => 'soft_delete', 'created_at' => now(),
            ]);
            if ($pet && ! $pet->trashed()) {
                app(PetCareReminders::class)->generateForPet($pet);
            }
        });
    }
}
