<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RecordAudit
{
    public function write(Model $record, User $actor, string $action, ?string $reason = null, array $changes = []): void
    {
        DB::table('record_change_audits')->insert([
            'actor_id' => $actor->id, 'record_table' => $record->getTable(), 'record_id' => $record->id,
            'action' => $action, 'reason' => $reason,
            'changes' => $changes ? json_encode($changes, JSON_THROW_ON_ERROR) : null, 'created_at' => now(),
        ]);
    }

    // Called inside the same transaction and lock as the edit; only validated record fields enter the audit.
    public function update(Model $record, array $values, User $actor, ?string $reason = null): void
    {
        $before = $record->getRawOriginal();
        $record->fill($values);
        $changes = [];
        foreach ($record->getDirty() as $field => $value) {
            $changes[$field] = ['before' => $before[$field] ?? null, 'after' => $value];
        }
        $record->save();
        if ($changes) $this->write($record, $actor, 'updated', $reason, $changes);
    }
}
