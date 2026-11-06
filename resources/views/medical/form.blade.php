@extends('layouts.app')
@php
    $label = $type === 'vaccinations' ? 'Vaccination' : 'Deworming';
    $editing = $record !== null;
    if ($editing) {
        $values['administered_at'] = $record->administered_at->format('Y-m-d');
        $values['next_due_at'] = $record->next_due_at?->format('Y-m-d');
    }
@endphp
@section('title', 'PawID | '.($editing ? 'Edit ' : 'Add ').$label.' Record')
@section('content')
    <div class="container-fluid">
        <a href="{{ route($type.'.index') }}" class="small text-decoration-none">&larr; Back to {{ $type === 'vaccinations' ? 'Vaccinations' : 'Deworming' }}</a>
        <h1 class="page-title mt-3 mb-4">{{ $editing ? 'Edit' : 'Add' }} {{ $label }} Record</h1>
        @unless ($editing) @include('medical.steps', ['step' => 3]) @endunless
        @include('medical.identity')
        <section class="card card-light p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">{{ $label }} Details</h2>
                @unless ($editing)<a href="{{ route($type.'.create', ['owner_id' => $owner->id]) }}" class="btn btn-outline-secondary btn-sm">Change Pet</a>@endunless
            </div>
            @if ($editing)<p class="text-muted">Correct the treatment details below. The owner and pet cannot be changed.</p>@endif
            <form method="POST" action="{{ $editing ? route($type.'.update', $record) : route($type.'.review') }}">
                @csrf
                @if ($editing)
                    @method('PUT')
                @else
                    <input type="hidden" name="owner_id" value="{{ $owner->id }}">
                    <input type="hidden" name="pet_id" value="{{ $pet->id }}">
                @endif
                <div class="row g-3">
                    @php $treatmentField = $type === 'vaccinations' ? 'vaccine' : 'product'; @endphp
                    <div class="col-md-6">
                        <label for="treatment" class="form-label">{{ $type === 'vaccinations' ? 'Vaccine' : 'Deworming product' }} *</label>
                        <input id="treatment" name="{{ $treatmentField }}" value="{{ old($treatmentField, $values[$treatmentField] ?? '') }}" maxlength="120" class="form-control @error($treatmentField) is-invalid @enderror" required>
                        @error($treatmentField)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="administered_at" class="form-label">Date administered *</label>
                        <input id="administered_at" name="administered_at" type="date" value="{{ old('administered_at', $values['administered_at'] ?? '') }}" max="{{ today()->toDateString() }}" class="form-control @error('administered_at') is-invalid @enderror" required>
                        @error('administered_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="next_due_at" class="form-label">Next due date</label>
                        <input id="next_due_at" name="next_due_at" type="date" value="{{ old('next_due_at', $values['next_due_at'] ?? '') }}" class="form-control @error('next_due_at') is-invalid @enderror">
                        @error('next_due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($type === 'vaccinations')
                        <div class="col-md-6">
                            <label for="veterinarian" class="form-label">Veterinarian / administrator</label>
                            <input id="veterinarian" name="veterinarian" value="{{ old('veterinarian', $values['veterinarian'] ?? '') }}" maxlength="120" class="form-control @error('veterinarian') is-invalid @enderror">
                            @error('veterinarian')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div class="col-md-6">
                            <label for="weight_kg" class="form-label">Weight (kg)</label>
                            <input id="weight_kg" name="weight_kg" type="number" min="0" max="999.99" step="0.01" value="{{ old('weight_kg', $values['weight_kg'] ?? '') }}" class="form-control @error('weight_kg') is-invalid @enderror">
                            @error('weight_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="1000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $values['notes'] ?? '') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    @if($editing)
                        <div class="flex-grow-1"><label for="audit_reason" class="form-label">Reason for correction (recommended)</label><input class="form-control" id="audit_reason" name="audit_reason" maxlength="500" value="{{ old('audit_reason') }}"></div>
                    @endif
                    <button class="btn btn-brand" type="submit">{{ $editing ? 'Save Changes' : 'Review Record' }}</button>
                    <a href="{{ route($type.'.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </div>
@endsection
