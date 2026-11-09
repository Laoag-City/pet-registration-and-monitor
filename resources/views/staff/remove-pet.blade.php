@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | Remove Pet')
@section('content')
<div class="container-fluid px-0" style="max-width:720px">
    <h1 class="page-title mb-3">Remove Pet?</h1>
    <div class="card card-light p-4">
        <p>You are about to remove this pet from active records.</p>
        <dl class="row">
            <dt class="col-sm-3">Pet</dt><dd class="col-sm-9">{{ $pet->name }}</dd>
            <dt class="col-sm-3">Owner</dt><dd class="col-sm-9">{{ $pet->owner?->name ?? $pet->owner_name ?? 'Not specified' }}</dd>
        </dl>
        <p>The pet will no longer appear in active lists, care schedules, or QR scans. Its details, photo, {{ $pet->vaccinations_count }} vaccination records, and {{ $pet->deworming_records_count }} deworming records will remain stored. Existing notification history will be retained, but reminders for this pet will stop.</p>
        <form method="POST" action="{{ route('staff.pets.destroy', $pet) }}">
            @csrf
            @method('DELETE')
            <div class="form-check mb-4">
                <input id="confirm-removal" class="form-check-input @error('confirm_removal') is-invalid @enderror" type="checkbox" name="confirm_removal" value="1" required>
                <label class="form-check-label" for="confirm-removal">I confirm that I want to remove {{ $pet->name }} from active records.</label>
                @error('confirm_removal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('pets.show', $pet) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-danger">Remove Pet</button>
            </div>
        </form>
    </div>
</div>
@endsection
