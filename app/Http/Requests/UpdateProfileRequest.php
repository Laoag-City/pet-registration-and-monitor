<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected $redirectRoute = 'profile.edit';

    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $maximum = $this->user()->pets()->withTrashed()->exists() ? 120 : 255;

        return [
            'name' => ['required', 'string', 'max:'.$maximum],
            'email' => ['required', 'string', 'email', 'max:'.$maximum, Rule::unique('users', 'email')->ignore($this->user()->id)],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'pet_id' => ['prohibited'],
            'role' => ['prohibited'],
            'is_admin' => ['prohibited'],
            'is_staff' => ['prohibited'],
            'permissions' => ['prohibited'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'remember_token' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
