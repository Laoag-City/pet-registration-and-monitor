<?php

namespace App\Exports;

use App\Models\DewormingRecord;
use Illuminate\Database\Eloquent\Builder;

class DewormingRecordsExport extends MedicalRecordsExport
{
    public function query(): Builder
    {
        return $this->recordsQuery(DewormingRecord::class, ['product', 'weight_kg']);
    }

    public function headings(): array
    {
        return array_merge($this->ownerAndPetHeadings(), ['Deworming Product', 'Date Administered', 'Next Due Date', 'Weight (kg)', 'Schedule Completion Status', 'Actual Completion Date', 'Treatment Status']);
    }

    public function map($record): array
    {
        return array_merge($this->ownerAndPet($record), [
            $record->product,
            $record->administered_at?->format('M j, Y'),
            $record->next_due_at?->format('M j, Y'),
            $record->weight_kg,
        ], $this->completionColumns($record));
    }
}
