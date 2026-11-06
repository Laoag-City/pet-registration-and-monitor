<section class="card card-light overflow-hidden mb-4" aria-labelledby="schedule-heading">
    <div class="p-3 p-md-4 border-bottom">
        <h2 id="schedule-heading" class="h6 mb-1 fw-bold">Scheduled {{ $type === 'vaccinations' ? 'Vaccinations' : 'Deworming' }}</h2>
        <p class="record-meta mb-0">{{ $scheduledRecords->count() }} scheduled treatments. Only current treatments with a next due date appear here.</p>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Pet</th><th>Treatment</th><th>Next Due Date</th><th>Schedule Status</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
            <tbody>
                @forelse($scheduledRecords as $record)
                    @php $scheduleStatus = $record->next_due_at->isBefore(today()) ? ['overdue', 'Overdue'] : ($record->next_due_at->lte(today()->addDays(30)) ? ['due', 'Due soon'] : ['current', 'Up to date']); @endphp
                    <tr>
                        <td>{{ $record->pet->name }}</td><td>{{ $type === 'vaccinations' ? $record->vaccine : $record->product }}</td>
                        <td>{{ $record->next_due_at->format('M j, Y') }}</td>
                        <td><span class="status-badge status-{{ $scheduleStatus[0] }}">{{ $scheduleStatus[1] }}</span></td>
                        @if($canManage)<td><a class="btn btn-outline-secondary btn-sm" href="{{ route($type.'.edit', $record) }}">Edit</a>@include('medical.done-button')<a class="btn btn-outline-danger btn-sm ms-1" href="{{ route($type.'.delete', $record) }}">Delete</a></td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManage ? 5 : 4 }}" class="text-center text-muted py-4">No scheduled treatments. All administrations remain available in the complete medical history below.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
