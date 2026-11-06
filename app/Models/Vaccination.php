<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['pet_id', 'vaccine', 'administered_at', 'next_due_at', 'veterinarian', 'notes'];

    protected function casts(): array
    {
        return ['administered_at' => 'date', 'next_due_at' => 'date', 'schedule_completed_at' => 'date'];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
