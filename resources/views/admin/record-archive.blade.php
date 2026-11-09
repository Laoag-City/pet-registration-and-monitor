@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | Record Archive')
@section('content')
<h1 class="page-title">Record Archive</h1>
<p class="text-muted">Restore an accidentally deleted record with a reason. Restoring a dated medical record may make its reminder active again. No pet or owner is restored by this action.</p>
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Record types">
@foreach(array_keys(\App\Http\Controllers\RecordArchiveController::TYPES) as $option)
<a class="btn {{ $type === $option ? 'btn-brand' : 'btn-outline-secondary' }}" href="{{ route('admin.record-archive', $option) }}">{{ ucwords(str_replace('-', ' ', $option)) }}</a>
@endforeach
<a class="btn btn-outline-secondary" href="{{ route('admin.record-audit') }}">Audit trail</a>
</nav>
@foreach($records as $record)
<section class="card card-light p-3 mb-3">
<h2 class="h5">{{ $medical ? $record->pet?->name : $record->farmer_name }}</h2>
<p>{{ $medical ? ($record->vaccine ?? $record->product) : ($record->barangay.' · '.($record->activity ?? $record->species)) }}<br>
{{ ($medical ? $record->administered_at : $record->visit_date)->format('M j, Y') }} · Deleted {{ $record->deleted_at->format('M j, Y H:i') }}
@if($medical)<br>Next due: {{ $record->next_due_at?->format('M j, Y') ?? 'Not Scheduled' }}@endif</p>
<form method="POST" action="{{ route('admin.record-archive.restore', [$type, $record->id]) }}" data-prevent-repeat>
@csrf
<label class="form-label" for="reason-{{ $record->id }}">Reason for restoring</label>
<input class="form-control mb-2" id="reason-{{ $record->id }}" name="reason" minlength="5" maxlength="500" value="{{ old('reason') }}" required>
<div class="form-check mb-2"><input class="form-check-input" id="confirm-{{ $record->id }}" type="checkbox" name="confirm_restore" value="1" required><label class="form-check-label" for="confirm-{{ $record->id }}">I confirm restoration of this record.</label></div>
<button class="btn btn-brand">Restore Record</button>
</form>
</section>
@endforeach
@if($records->isEmpty())<div class="alert alert-info">No deleted records in this category.</div>@endif
{{ $records->links() }}
@endsection
