@extends('layouts.app')
@section('title', 'PawID | Edit Pet')
@section('content')
<div class="container-fluid px-0">
    <h1 class="page-title mb-1">Edit Pet</h1>
    <p class="text-muted mb-4">Update details for {{ $pet->name }}.</p>
    <div class="card card-light p-4">
        <div class="mb-4 pb-3 border-bottom">
            <div class="detail-label">Current owner</div>
            <div class="fw-semibold">{{ $pet->owner?->name ?? $pet->owner_name ?? 'Not specified' }}</div>
            <div class="record-meta">Ownership stays unchanged when editing pet details.</div>
            @if ($pet->photo_path)
                <img class="rounded mt-3" src="{{ asset('storage/'.$pet->photo_path) }}" alt="Current photo of {{ $pet->name }}" style="width:80px;height:80px;object-fit:cover">
            @endif
        </div>
        <form method="POST" action="{{ route('staff.pets.update', $pet) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="row g-3">
                @include('staff.pet-species')
                @foreach (['name' => ['Pet name', 120, true], 'breed' => ['Breed', 120, false], 'color' => ['Color', 80, false]] as $field => [$label, $limit, $required])
                    <div class="col-md-6">
                        <label class="form-label" for="{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $pet->$field) }}" maxlength="{{ $limit }}" @required($required) class="form-control @error($field) is-invalid @enderror">
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <div class="col-md-6">
                    <label class="form-label" for="sex">Sex *</label>
                    <select id="sex" name="sex" required class="form-select @error('sex') is-invalid @enderror">
                        @foreach (['Male', 'Female'] as $sex)<option value="{{ $sex }}" @selected(old('sex', $pet->sex) === $sex)>{{ $sex }}</option>@endforeach
                    </select>
                    @error('sex')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="date_of_birth">Date of birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" max="{{ today()->toDateString() }}" value="{{ old('date_of_birth', $pet->date_of_birth?->toDateString()) }}" class="form-control @error('date_of_birth') is-invalid @enderror">
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="photo">Replace photo (optional)</label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
                    @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-brand" type="submit">Save Changes</button><a class="btn btn-outline-secondary" href="{{ route('pets.show', $pet) }}">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
