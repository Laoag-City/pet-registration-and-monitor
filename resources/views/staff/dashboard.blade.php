@extends('layouts.app')
@section('title', 'PawID | Staff Dashboard')
@section('body-class', 'staff-dashboard-page')
@push('styles')
    @vite(['resources/css/staff-dashboard.css'])
@endpush
@section('content')
<div class="staff-dashboard" data-staff-dashboard>
    <header class="staff-welcome mb-4">
        <div>
            <div class="page-kicker mb-2">OCV Staff</div>
            <h1 class="page-title mb-2">Welcome back, {{ $user->name }}</h1>
            <p class="text-muted mb-0">Office of the City Veterinarian workspace.</p>

        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('staff.users') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1" aria-hidden="true"></i>User Management</a>
            <a href="{{ route('staff.pets.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Register Pet</a>
        </div>
        <div class="welcome-art" aria-hidden="true">
            <svg viewBox="0 0 220 125"><g fill="currentColor"><path d="M18 113c-17-7-15-28-9-32 4-2 6 2 3 7-5 12 4 19 13 18l5-31-5-16 9 4 8-13 6 14c14 11 7 23 3 28l9 21z"/><path d="M105 115c-6-23-6-39 7-55l6-9c-4-9-4-21 3-31l7-14 19-4 17 5 4 10 17 5-2 12-18 5-11-2c-4 15 3 28 13 45 6 10 9 22 7 32l17 3v5h-62l-3-36-7 30z"/><path d="M65 115l4-20-3-12 6 2 5-8 5 9c9 6 8 15 5 20l5 9z"/><rect x="0" y="120" width="215" height="2" rx="1"/></g><path fill="#e6cfdc" d="M86 29C66 11 61 33 86 44c24-13 18-34 0-15"/></svg>
            <span>Healthy Pets,<br>Happier Communities</span>
        </div>
    </header>
    <div class="row g-3 mb-4">
        @foreach ([['Registered Clients', $userCount, 'bi-people', 'blue', 'staff.users'], ['Registered Pets', $petCount, 'bi-heart', 'green', 'staff.pets'], ['Vaccination Records', $vaccinationCount, 'bi-shield-check', 'pink', 'vaccinations.index'], ['Deworming Records', $dewormingCount, 'bi-capsule', 'orange', 'deworming.index']] as [$label, $count, $icon, $tone, $route])
            <div class="col-12 col-sm-6 col-xl-3"><a class="staff-stat card-light" href="{{ route($route) }}"><span class="staff-icon tone-{{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></span><span><span class="metric-label d-block">{{ $label }}</span><span class="metric-value d-block mt-2">{{ number_format($count) }}</span><span class="stat-caption">Total registered</span></span><i class="bi {{ $icon }} stat-watermark tone-text-{{ $tone }}" aria-hidden="true"></i></a></div>
        @endforeach
    </div>
    <h2 class="visually-hidden">Analytics overview</h2>
    <div class="staff-analytics">
        <section class="card-light analytics-card registration-card">
            <div class="d-flex justify-content-between gap-2"><div><h3><i class="bi bi-bar-chart-fill heading-icon" aria-hidden="true"></i>Pet Registrations Over Time</h3><p class="record-meta">Monthly pet registrations for the current year</p></div><span class="year-label">{{ $year }}</span></div>
            <div class="trend-chart"><canvas id="registration-chart" role="img" aria-label="Monthly pet registrations in {{ $year }}. Text values are available below."></canvas></div>
            @if ($yearCount === 0)<p class="record-meta text-center mt-2">No pets registered this year.</p>@endif
            <div class="trend-footer"><div><i class="bi bi-graph-up-arrow" aria-hidden="true"></i><strong>{{ number_format($yearCount) }}</strong><span>registered this year</span></div><div><i class="bi bi-file-earmark-text" aria-hidden="true"></i><strong>{{ number_format($thisMonthCount) }}</strong><span>registered this month</span></div></div>
            <details class="chart-data mt-3"><summary>View monthly counts</summary><dl class="monthly-counts mt-2 mb-0">@foreach ($registrations as $count)<div><dt>{{ \Carbon\CarbonImmutable::create($year, $loop->iteration, 1)->format('M') }}</dt><dd>{{ $count }}</dd></div>@endforeach</dl></details>
        </section>
        <section class="card-light analytics-card species-card">
            <h3><i class="bi bi-pie-chart-fill heading-icon" aria-hidden="true"></i>Pets by Species</h3><p class="record-meta">Distribution of registered pets</p>
            <div class="donut-wrap species-donut"><canvas id="species-chart" role="img" aria-label="Species distribution. Counts and percentages are listed below."></canvas><div class="donut-center"><svg class="center-paw" viewBox="0 0 100 100" aria-hidden="true"><ellipse cx="22" cy="32" rx="10" ry="15" transform="rotate(-25 22 32)"/><ellipse cx="43" cy="22" rx="10" ry="15"/><ellipse cx="65" cy="26" rx="10" ry="15"/><ellipse cx="81" cy="43" rx="10" ry="15" transform="rotate(25 81 43)"/><path d="M27 66Q49 28 73 66Q87 91 53 81Q17 93 27 66"/></svg><strong>{{ number_format($petCount) }}</strong><span>total pets</span></div></div>
            <ul class="chart-legend species-legend">
                @forelse ($species as $item)
                    <li><span class="legend-name"><span class="legend-dot" data-species-dot="{{ $loop->index }}"></span>{{ $item['label'] }}</span><span><strong>{{ $item['count'] }}</strong> <span class="text-muted">({{ $item['percentage'] }}%)</span></span></li>
                @empty
                    <li class="record-meta justify-content-center">No pets registered yet.</li>
                @endforelse
            </ul>
        </section>
        @foreach ($statuses as $type => $status)
            <section class="card-light analytics-card {{ $type }}-card">
                <h3><i class="bi bi-shield-check heading-icon care-heading-icon" aria-hidden="true"></i>{{ ucfirst($type) }} Status</h3><p class="record-meta">Current status of {{ $type }} records</p>
                <div class="status-content">
                    <div class="donut-wrap status-donut"><canvas id="{{ $type }}-chart" role="img" aria-label="{{ ucfirst($type) }} schedule status. Counts are listed alongside."></canvas><div class="donut-center"><strong>{{ $status['percentage'] === null ? '—' : $status['percentage'].'%' }}</strong><span>{{ $status['total'] ? 'Up to Date' : 'No schedules' }}</span>@if ($status['total'])<span class="donut-detail">{{ $status['counts']['Up to Date'] }} of {{ $status['total'] }} schedules</span>@endif</div></div>
                    <ul class="chart-legend status-legend">@foreach ($status['counts'] as $label => $count)<li><span class="legend-name"><span class="legend-dot status-dot-{{ $loop->index }}"></span>{{ $label }}</span><strong>{{ $count }}</strong></li>@endforeach</ul>
                </div>
                <p class="status-note">{{ $status['total'] }} current {{ \Illuminate\Support\Str::plural('schedule', $status['total']) }}@if ($status['unscheduled']) &middot; {{ $status['unscheduled'] }} without a due date excluded @endif</p>
            </section>
        @endforeach
        <section class="card-light analytics-card attention-card">
            <h3><i class="bi bi-bell-fill heading-icon" aria-hidden="true"></i>Upcoming &amp; Overdue</h3><p class="record-meta">Pets with upcoming or overdue schedules</p>
            <ul class="attention-list">
                @forelse ($attention as $item)
                    <li><span class="attention-pet-icon"><i class="bi bi-heart" aria-hidden="true"></i></span><div class="attention-details"><a class="record-name" href="{{ route('pets.show', $item['pet']) }}">{{ $item['pet']->name }}</a><span class="record-meta d-block">{{ $item['type'] }}</span></div><span class="attention-date"><i class="bi bi-calendar4-event" aria-hidden="true"></i> {{ $item['due']->format('M j, Y') }}</span><span class="care-badge care-{{ $item['priority'] }}">{{ $item['status'] }}</span></li>
                @empty
                    <li class="attention-empty"><i class="bi bi-calendar-check" aria-hidden="true"></i><p class="mb-0">No upcoming or overdue pet care schedules.</p></li>
                @endforelse
            </ul>
            <div class="attention-footer"><i class="bi bi-calendar2-week me-2" aria-hidden="true"></i><div><strong>{{ $attentionCount }}</strong><span>{{ \Illuminate\Support\Str::plural('pet', $attentionCount) }} {{ $attentionCount === 1 ? 'needs' : 'need' }} attention</span></div></div>
        </section>
    </div>
    <p class="record-meta mt-3 mb-0">Care status reflects current schedules with a due date. Due Soon includes the next 7 days; each vaccine has its own schedule.</p>
    <script type="application/json" id="staff-chart-data">{!! json_encode(['registrations' => $registrations, 'species' => $species, 'statuses' => $statuses], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
</div>
@endsection
@push('scripts')
    @vite(['resources/js/staff-dashboard.js'])
@endpush
