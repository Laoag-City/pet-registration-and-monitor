<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class PetSpecies
{
    public static function rules(): array
    {
        return [
            'species' => ['required', Rule::in(['Canine', 'Feline', 'Others'])],
            'species_other' => ['exclude_unless:species,Others', 'required', 'string', 'max:60', Rule::notIn(['Others', 'others'])],
        ];
    }

    public static function stored(array $values): string
    {
        return $values['species'] === 'Others' ? $values['species_other'] : $values['species'];
    }

    public static function selection(?string $value): string
    {
        return match (mb_strtolower(trim($value ?? ''))) {
            '' => '', 'dog', 'canine' => 'Canine', 'cat', 'feline' => 'Feline', default => 'Others',
        };
    }
}
