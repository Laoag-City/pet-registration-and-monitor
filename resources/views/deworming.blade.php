@extends('layouts.app')
@php $canManage = auth()->user()?->canManageRecords() ?? false; @endphp
@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-end gap-3 mb-4">
        <div><div class="page-kicker">Health records</div><h1 class="page-title mb-1">Deworming</h1><p class="text-muted mb-0">Track parasite prevention and upcoming treatments for every pet.</p></div>
        @if ($canManage)
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-success btn-sm" href="{{ route('deworming.export') }}" title="Download all deworming records for active pets"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Download Excel</a>
                <a class="btn btn-brand btn-sm" href="{{ route('deworming.create') }}"><i class="bi bi-plus-lg me-1"></i>Add Deworming Record</a>
            </div>
        @elseif ($pets->isEmpty())
            <span class="record-meta">No pets registered yet.</span>
        @endif
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3"><div class="card card-light p-3 h-100"><div class="metric-label">Treatment records</div><div class="metric-value mt-1">{{ $stats['total'] }}</div><div class="record-meta mt-1">Across registered pets</div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card card-light p-3 h-100"><div class="metric-label">Up to date</div><div class="metric-value mt-1">{{ $stats['current'] }}</div><div class="record-meta mt-1">No action needed</div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card card-light p-3 h-100"><div class="metric-label">Due soon</div><div class="metric-value mt-1">{{ $stats['dueSoon'] }}</div><div class="record-meta mt-1">Within the next 30 days</div></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="card card-light p-3 h-100"><div class="metric-label">Overdue</div><div class="metric-value text-danger mt-1">{{ $stats['overdue'] }}</div><div class="record-meta mt-1">Needs attention</div></div></div>
    </div>

    <section class="card card-light overflow-hidden">
        <div class="d-flex justify-content-between align-items-center gap-2 p-3 p-md-4 border-bottom"><div><h2 class="h6 mb-1 fw-bold">Recent Deworming</h2><p class="record-meta mb-0">{{ $records->count() }} {{ Str::plural('record', $records->count()) }} saved</p><p class="record-meta mb-0 mt-1">Previous records are kept as treatment history. Only the latest deworming schedule for each pet is used for reminders and care status.</p></div></div>
        <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Pet</th><th>Product</th><th>Date given</th><th>Next due</th><th>Weight</th><th>Status</th>@if ($canManage)<th>Actions</th>@endif</tr></thead><tbody>
            @forelse ($records as $record)
                @php $status = !$record->next_due_at ? ['current', 'No due date', 'bi-check2-circle'] : ($record->next_due_at->isBefore(today()) ? ['overdue', 'Overdue', 'bi-exclamation-circle'] : ($record->next_due_at->lte(today()->addDays(30)) ? ['due', 'Due soon', 'bi-clock'] : ['current', 'Up to date', 'bi-check2-circle'])); @endphp
                @php if (!$currentRecordIds->contains($record->id)) { $status = ['pending', 'Previous record', 'bi-clock-history']; } @endphp
                <tr><td><div class="record-name">{{ $record->pet->name }}</div><div class="record-meta">{{ $record->pet->breed ?? $record->pet->species }}</div></td><td>{{ $record->product }}</td><td>{{ $record->administered_at->format('M j, Y') }}</td><td>{{ $record->next_due_at?->format('M j, Y') ?? 'Not scheduled' }}</td><td>{{ $record->weight_kg ? $record->weight_kg.' kg' : 'Not recorded' }}</td><td><span class="status-badge status-{{ $status[0] }}"><i class="bi {{ $status[2] }}"></i>{{ $status[1] }}</span></td>@if ($canManage)<td><a class="btn btn-outline-secondary btn-sm" href="{{ route('deworming.edit', $record) }}">Edit<span class="visually-hidden"> Deworming record for {{ $record->pet->name }}</span></a></td>@endif</tr>
            @empty
                <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-muted py-5">No deworming records yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

@endsection
