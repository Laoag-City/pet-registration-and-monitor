@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | Notifications')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="page-title mb-1">Notifications</h1><p class="text-muted mb-0">Vaccination and deworming reminders for your pets.</p></div>
        @if ($careUnreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-outline-secondary" type="submit">Mark All as Read</button></form>
        @endif
    </div>
    <div class="d-grid gap-3">
        @forelse ($notifications as $notification)
            @php $schedule = $schedules[$notification->data['schedule_key']]; @endphp
            <article class="card card-light p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <h2 class="h6"><a href="{{ route('pets.show', $schedule['pet']) }}">{{ $schedule['pet']->name }}</a> &middot; {{ ucfirst($schedule['type']) }}</h2>
                        @include('notifications.schedule', ['schedule' => $schedule])
                        <div class="record-meta mt-2">{{ $notification->read_at ? 'Read' : 'Unread' }} &middot; {{ $notification->data['stage'] }} reminder</div>
                    </div>
                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-outline-secondary btn-sm" type="submit">Mark as Read<span class="visually-hidden">: {{ $schedule['pet']->name }} {{ $schedule['type'] }}</span></button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="card card-light p-4 text-muted">No upcoming vaccination or deworming reminders.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $notifications->links() }}</div>
</div>
@endsection
