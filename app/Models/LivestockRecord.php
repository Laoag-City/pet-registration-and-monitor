<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class LivestockRecord extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const SPECIES = ['Bubaline', 'Bovine', 'Caprine', 'Swine', 'Equine', 'Ovine'];

    public const SEXES = ['Male', 'Female'];

    public const AGE_UNITS = ['days', 'months', 'years'];

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'age_value' => 'decimal:2', 'cases' => 'integer', 'number_of_heads' => 'integer'];
    }

    public function ageLabel(): string
    {
        return $this->age_value === null ? '' : rtrim(rtrim($this->age_value, '0'), '.').' '.$this->age_unit;
    }

    public function scopeFiltered(Builder $query, array $filters): void
    {
        $search = trim($filters['q'] ?? '');
        if (! empty($filters['barangay'])) {
            $query->where('barangay', $filters['barangay']);
        }
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->whereLike('farmer_name', '%'.$search.'%')
                    ->orWhereLike('barangay', '%'.$search.'%')
                    ->orWhereLike('species', '%'.$search.'%');
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('visit_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('visit_date', '<=', $filters['date_to']);
        }
    }
}
