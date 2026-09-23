<?php

namespace App\Http\Requests;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageRecords() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'administered_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'next_due_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:administered_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->routeIs('vaccinations.*')) {
            $rules['vaccine'] = ['required', 'string', 'max:120'];
            $rules['veterinarian'] = ['nullable', 'string', 'max:120'];
        } else {
            $rules['product'] = ['required', 'string', 'max:120'];
            $rules['weight_kg'] = ['nullable', 'numeric', 'min:0', 'max:999.99'];
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            // Corrections never move a medical record to another pet or owner.
            $rules['owner_id'] = ['prohibited'];
            $rules['pet_id'] = ['prohibited'];
        } else {
            $rules['owner_id'] = ['bail', 'required', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_USER)];
            $rules['pet_id'] = ['bail', 'required', 'integer', 'exists:pets,id'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->isMethod('PUT') || $this->isMethod('PATCH')
                || $validator->errors()->hasAny(['owner_id', 'pet_id'])) {
                return;
            }

            $owner = User::where('role', User::ROLE_USER)->find($this->input('owner_id'));
            if (!$owner || !Pet::whereKey($this->input('pet_id'))->where('owner_email', $owner->email)->exists()) {
                $validator->errors()->add('pet_id', 'The selected pet does not belong to the selected owner.');
            }
        }];
    }

    protected function getRedirectUrl(): string
    {
        $type = $this->routeIs('vaccinations.*') ? 'vaccinations' : 'deworming';
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return route($type.'.edit', $this->route($type === 'vaccinations' ? 'vaccination' : 'deworming'));
        }

        // Validation after the POST-only review page must return to a GET form.
        $ownerId = filter_var($this->input('owner_id'), FILTER_VALIDATE_INT);
        $petId = filter_var($this->input('pet_id'), FILTER_VALIDATE_INT);
        $owner = $ownerId ? User::where('role', User::ROLE_USER)->find($ownerId) : null;
        $parameters = $owner ? ['owner_id' => $owner->id] : [];
        if ($owner && $petId && $owner->pets()->whereKey($petId)->exists()) {
            $parameters['pet_id'] = $petId;
        }

        return route($type.'.create', $parameters);
    }
}
