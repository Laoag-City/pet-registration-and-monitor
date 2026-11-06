<?php

namespace App\Exports;

use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Builder;

class VaccinationsExport extends MedicalRecordsExport
{
    public function query(): Builder
    {
        return $this->recordsQuery(Vaccination::class, ['vaccine', 'veterinarian']);
    }

    public function headings(): array
    {
        return array_merge($this->ownerAndPetHeadings(), ['Vaccine Name', 'Date Administered', 'Next Due Date', 'Veterinarian', 'Schedule Completion Status', 'Actual Completion Date', 'Treatment Status']);
    }

    public function map($record): array
    {
        return array_merge($this->ownerAndPet($record), [
            $record->vaccine,
            $record->administered_at?->format('M j, Y'),
            $record->next_due_at?->format('M j, Y'),
            $record->veterinarian,
        ], $this->completionColumns($record));
    }
}
