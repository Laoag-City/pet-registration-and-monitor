<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// StringValueBinder preserves phone-number zeros and prevents cell formula injection.
abstract class MedicalRecordsExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles
{
    protected function recordsQuery(string $model, array $columns): Builder
    {
        // Match the medical pages: archived pets are excluded by Pet's soft-delete scope.
        return $model::query()
            ->select(array_merge(['id', 'pet_id', 'administered_at', 'next_due_at'], $columns))
            ->whereHas('pet')
            ->with([
                'pet:id,name,species,breed,sex,owner_name,owner_email,owner_number',
                'pet.owner:id,name,email,contact_number',
            ])
            ->orderByDesc('administered_at')
            ->orderByDesc('id');
    }

    protected function ownerAndPet($record): array
    {
        $pet = $record->pet;
        $owner = $pet?->owner;

        return [
            $owner?->name ?? $pet?->owner_name,
            $owner?->email ?? $pet?->owner_email,
            $owner?->contact_number ?? $pet?->owner_number,
            $pet?->name,
            $pet?->species,
            $pet?->breed,
            $pet?->sex,
        ];
    }

    protected function ownerAndPetHeadings(): array
    {
        return ['Owner Name', 'Owner Email', 'Owner Contact Number', 'Pet Name', 'Species', 'Breed', 'Sex'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

        return [1 => ['font' => ['bold' => true]]];
    }
}
