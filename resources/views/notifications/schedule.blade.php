<div class="record-meta mb-2">{{ $schedule['treatment'] }}</div>
<p class="mb-2">
    @if ($schedule['days'] < 0)
        {{ $schedule['pet']->name }}'s {{ $schedule['type'] }} was due on {{ $schedule['due']->format('F j, Y') }} and is now overdue.
    @elseif ($schedule['days'] === 0)
        {{ $schedule['pet']->name }}'s {{ $schedule['type'] }} is due today.
    @else
        {{ $schedule['pet']->name }}'s {{ $schedule['type'] }} is due on {{ $schedule['due']->format('F j, Y') }}.
    @endif
</p>
<span class="status-badge {{ $schedule['days'] < 0 ? 'status-overdue' : 'status-due' }}">{{ $schedule['status'] }}</span>
@if ($schedule['days'] > 0)<span class="small text-muted ms-2">{{ $schedule['days'] }} {{ Str::plural('day', $schedule['days']) }} remaining</span>@endif
@if ($schedule['days'] === 0)<span class="small text-muted ms-2">{{ $schedule['due']->format('F j, Y') }}</span>@endif
