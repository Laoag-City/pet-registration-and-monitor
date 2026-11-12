<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageRecords() ?? false;
    }

    public function rules(): array
    {
        return \App\Support\PetSpecies::rules() + [
            'name' => ['required', 'string', 'max:120'],
            'breed' => ['nullable', 'string', 'max:120'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'color' => ['nullable', 'string', 'max:80'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'id' => ['prohibited'], 'pet_id' => ['prohibited'], 'owner_id' => ['prohibited'],
            'owner_email' => ['prohibited'], 'owner_name' => ['prohibited'], 'owner_number' => ['prohibited'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('staff.pets.edit', $this->route('pet'));
    }
}
