{{-- administered_at is the recorded actual treatment date; next_due_at is a separate obligation. --}}
@if ($record->administered_at && $record->administered_at->lte(today()))
    <span class="status-badge status-current"><i class="bi bi-check2-circle"></i>Completed</span>
@else
    <span class="status-badge status-pending">Administration not confirmed</span>
@endif
<div class="record-meta mt-1">Next treatment: {{ $status[1] }}</div>
@if($record->schedule_completed_at)
    <div class="record-meta">Schedule completed {{ $record->schedule_completed_at->format('M j, Y') }}</div>
@endif
