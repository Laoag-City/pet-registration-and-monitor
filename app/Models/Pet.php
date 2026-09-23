<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pet extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['name', 'species', 'breed', 'sex', 'date_of_birth', 'color', 'photo_path', 'owner_name', 'owner_email', 'owner_number'];

    // Serialize only supported fields and relationships, excluding obsolete storage columns.
    protected $visible = [
        'id', 'code', 'name', 'species', 'breed', 'sex', 'date_of_birth', 'color',
        'photo_path', 'owner_name', 'owner_email', 'owner_number',
        'created_at', 'updated_at', 'deleted_at',
        'owner', 'vaccinations', 'dewormingRecords', 'deworming_records',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    protected static function booted(): void
    {
        static::created(function (self $pet): void {
            $pet->forceFill(['code' => 'PET-'.str_pad((string) $pet->id, 4, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_email', 'email');
    }

    public function dewormingRecords(): HasMany
    {
        return $this->hasMany(DewormingRecord::class);
    }
}
