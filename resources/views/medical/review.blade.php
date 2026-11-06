@extends('layouts.app')
@php $label = $type === 'vaccinations' ? 'Vaccination' : 'Deworming'; @endphp
@section('title', 'PawID | Review '.$label.' Record')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">Review {{ $label }} Record</h1>
        @include('medical.steps', ['step' => 4])
        @include('medical.identity')
        <section class="card card-light p-3 p-md-4">
            <h2 class="h5 mb-3">Review and Save</h2>
            <p class="text-muted">Confirm the owner, pet, and treatment details before saving.</p>
            @php
                $fields = $type === 'vaccinations'
                    ? ['vaccine' => 'Vaccine', 'administered_at' => 'Date administered', 'next_due_at' => 'Next due date', 'veterinarian' => 'Veterinarian / administrator', 'notes' => 'Notes']
                    : ['product' => 'Deworming product', 'administered_at' => 'Date administered', 'next_due_at' => 'Next due date', 'weight_kg' => 'Weight (kg)', 'notes' => 'Notes'];
            @endphp
            <dl class="row mb-4">
                @foreach ($fields as $field => $fieldLabel)
                    <dt class="col-md-4 mb-2">{{ $fieldLabel }}</dt>
                    <dd class="col-md-8 text-break" style="white-space:pre-wrap">{{ ($values[$field] ?? '') !== '' && ($values[$field] ?? null) !== null ? $values[$field] : 'Not provided' }}</dd>
                @endforeach
            </dl>
            <form method="POST" action="{{ route($type.'.store') }}" data-prevent-repeat>
                @csrf
                @foreach ($values as $field => $value)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endforeach
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-brand" type="submit">Save {{ $label }} Record</button>
                    <button class="btn btn-outline-secondary" type="submit" name="edit_details" value="1" formaction="{{ route($type.'.review') }}">Edit Details</button>
                    <a href="{{ route($type.'.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </div>
@endsection
