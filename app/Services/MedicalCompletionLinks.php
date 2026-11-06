<?php

namespace App\Services;

use App\Models\{Vaccination, DewormingRecord};
use Illuminate\Support\Facades\DB;

/** Resolve the exact source/administration IDs written atomically by Done. Never infer links from names/dates. */
class MedicalCompletionLinks
{
    public function resolve(string $model): array
    {
        if (!in_array($model, [Vaccination::class, DewormingRecord::class], true)) {
            throw new \InvalidArgumentException('Unsupported medical model.');
        }
        $table = (new $model)->getTable();
        $sources = $targets = [];
        foreach (DB::table('record_change_audits')->where('record_table', $table)->where('action', 'schedule_completed')->orderBy('id')->cursor() as $audit) {
            $data = json_decode($audit->changes ?? '', true);
            $target = is_array($data) ? ($data['administration_id'] ?? null) : null;
            $source = (int) $audit->record_id;
            // A malformed or conflicting audit must never silently suppress history.
            $target = filter_var($target, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $sources[$source][$target] = true;
            $targets[$target][$source] = true;
        }
        $rows = collect();
        foreach (array_chunk(array_unique(array_merge(array_keys($sources), array_keys($targets))), 500) as $ids) {
            $rows = $rows->union($model::withTrashed()->whereIn('id', $ids)->get(['id', 'pet_id', 'schedule_completed_at'])->keyBy('id'));
        }
        $pairs = [];
        foreach ($sources as $source => $choices) {
            if (count($choices) !== 1) continue;
            $target = array_key_first($choices);
            if (count($targets[$target]) !== 1 || $target <= $source) continue;
            $original = $rows->get($source);
            $administration = $rows->get($target);
            if (!$original?->schedule_completed_at || !$administration || $original->pet_id !== $administration->pet_id) continue;
            $pairs[$source] = $target;
        }
        // A completed administration may later carry its own next-due obligation.
        // In A -> B -> C, retain both real administrations B and C, never just the latest dose.
        return ['pairs' => $pairs, 'completed_ids' => array_values($pairs),
            'superseded_ids' => array_values(array_diff(array_keys($pairs), array_values($pairs)))];
    }
}
