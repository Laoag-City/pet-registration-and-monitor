@extends('layouts.app')
@section('content')
    <h1 class="page-title mb-4">Delete {{ $type === 'vaccinations' ? 'Vaccination' : 'Deworming' }} Record</h1>
    <section class="card card-light p-3 p-md-4">
        <dl class="row">
            <dt class="col-sm-4">Pet</dt><dd class="col-sm-8">{{ $record->pet?->name ?? 'Unavailable' }}</dd>
            <dt class="col-sm-4">Treatment</dt><dd class="col-sm-8">{{ $type === 'vaccinations' ? $record->vaccine : $record->product }}</dd>
            <dt class="col-sm-4">Administration date</dt><dd class="col-sm-8">{{ $record->administered_at->format('M j, Y') }}</dd>
        </dl>
        <p class="alert alert-warning">This removes only the selected record from active lists, exports and reminders. The record is retained for audit. The pet, owner and other treatments are not deleted.</p>
        <form method="POST" action="{{ route($type.'.destroy', $record) }}">
            @csrf @method('DELETE')
            <div class="form-check mb-3"><input class="form-check-input @error('confirm_delete') is-invalid @enderror" type="checkbox" id="confirm_delete" name="confirm_delete" value="1" required><label class="form-check-label" for="confirm_delete">I confirm deletion of this selected record.</label>@error('confirm_delete')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <button class="btn btn-danger" type="submit">Delete Record</button>
            <a class="btn btn-outline-secondary ms-2" href="{{ route($type.'.index') }}">Cancel</a>
        </form>
    </section>
@endsection
