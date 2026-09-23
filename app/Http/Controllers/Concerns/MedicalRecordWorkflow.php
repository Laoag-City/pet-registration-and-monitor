<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\MedicalRecordRequest;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

trait MedicalRecordWorkflow
{
    private function createMedicalRecord(Request $request, string $type): View
    {
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'required_with:pet_id'],
            'pet_id' => ['nullable', 'integer'],
        ]);
        $search = trim($input['q'] ?? '');
        $owner = isset($input['owner_id'])
            ? User::where('role', User::ROLE_USER)->findOrFail($input['owner_id'])
            : null;
        $pet = $owner && isset($input['pet_id'])
            ? $owner->pets()->findOrFail($input['pet_id'])
            : null;

        if ($pet) {
            return view('medical.form', compact('type', 'owner', 'pet') + ['record' => null, 'values' => []]);
        }

        $owners = $owner ? null : User::query()
            ->select('id', 'name', 'email', 'contact_number')
            ->where('role', User::ROLE_USER)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereLike('name', '%'.$search.'%')
                        ->orWhereLike('email', '%'.$search.'%')
                        ->orWhereLike('contact_number', '%'.$search.'%');
                });
            })
            ->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();
        $pets = $owner?->pets()->orderBy('name')->orderBy('id')->paginate(12)->withQueryString();

        return view('medical.select', compact('type', 'owner', 'owners', 'pets', 'search'));
    }

    private function reviewMedicalRecord(MedicalRecordRequest $request, string $type): View
    {
        $values = $request->validated();
        $owner = User::where('role', User::ROLE_USER)->findOrFail($values['owner_id']);
        $pet = $owner->pets()->findOrFail($values['pet_id']);

        if ($request->boolean('edit_details')) {
            return view('medical.form', compact('type', 'owner', 'pet', 'values') + ['record' => null]);
        }

        return view('medical.review', compact('type', 'owner', 'pet', 'values'));
    }

    // Called inside the save transaction: recheck ownership even after review.
    private function medicalRecordPet(array $values): Pet
    {
        $owner = User::where('role', User::ROLE_USER)->lockForUpdate()->find($values['owner_id']);
        $pet = $owner?->pets()->lockForUpdate()->find($values['pet_id']);

        if (!$pet) {
            throw ValidationException::withMessages([
                'pet_id' => 'The owner or pet selection has changed. Please select the owner and pet again.',
            ]);
        }

        return $pet;
    }
}
