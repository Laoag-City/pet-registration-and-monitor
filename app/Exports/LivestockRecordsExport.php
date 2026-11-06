<?php

namespace App\Exports;

use App\Models\AnimalHealthMonitoringRecord;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LivestockRecordsExport extends DefaultValueBinder implements FromQuery, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStrictNullComparison, WithStyles
{
    public function __construct(private string $model) {}

    private function isHealth(): bool
    {
        return $this->model === AnimalHealthMonitoringRecord::class;
    }

    public function query(): Builder
    {
        return $this->model::query()->orderByDesc('visit_date')->orderByDesc('id');
    }

    public function headings(): array
    {
        return $this->isHealth()
            ? ['Date of Visit', 'Barangay', "Farmer's Name", 'Species', 'Sex', 'Age', 'Cases', 'Clinical Signs', 'Disorder', 'Remarks']
            : ['Date of Visit', 'Barangay', "Farmer's Name", 'Activity', 'Species', 'Sex', 'Age', 'Number of Heads', 'Remarks'];
    }

    public function map($record): array
    {
        $row = [$record->visit_date->format('M j, Y'), $record->barangay, $record->farmer_name];
        if (! $this->isHealth()) {
            $row[] = $record->activity;
        }

        return array_merge($row, [$record->species, $record->sex, $record->ageLabel()], $this->isHealth()
            ? [$record->cases, $record->clinical_signs, $record->disorder, $record->remarks]
            : [$record->number_of_heads, $record->remarks]);
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Only actual counts are numeric; all user-entered text stays literal, never a formula.
        $cell->setValueExplicit($value, is_int($value) ? DataType::TYPE_NUMERIC : ($value === null ? DataType::TYPE_NULL : DataType::TYPE_STRING));

        return true;
    }

    public function columnWidths(): array
    {
        return $this->isHealth()
            ? ['A' => 18, 'B' => 24, 'C' => 28, 'D' => 16, 'E' => 12, 'F' => 18, 'G' => 12, 'H' => 40, 'I' => 28, 'J' => 40]
            : ['A' => 18, 'B' => 24, 'C' => 28, 'D' => 32, 'E' => 16, 'F' => 12, 'G' => 18, 'H' => 20, 'I' => 40];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');

        return [1 => ['font' => ['bold' => true]]];
    }
}
