@extends('layouts.app')

@section('title', 'PawID | Dashboard')

@section('content')
  <div class="container-fluid px-0">
    <div class="row mb-4 g-3"><div class="col"><div class="page-kicker">Overview</div><h1 class="page-title mb-1">Welcome back, {{ $user->name }}</h1><p class="text-muted mb-0">Here's how your pets are doing today.</p></div><div class="col-12 col-md-auto d-flex align-items-end">@if ($user->canManageRecords())<a href="{{ route('staff.pets.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>Register Pet</a>@endif</div></div>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3"><div class="card card-light p-3"><div class="metric-label">Registered Pets</div><div class="metric-value mt-1">{{ $petCount }}</div></div></div>
      <div class="col-6 col-md-3"><div class="card card-light p-3"><div class="metric-label">Vaccinations Given</div><div class="metric-value mt-1">{{ $vaccinationCount }}</div></div></div>
      <div class="col-6 col-md-3"><div class="card card-light p-3"><div class="metric-label">Deworming Records</div><div class="metric-value mt-1">{{ $dewormingCount }}</div></div></div>
    </div>
    <section class="card card-light p-4 mb-4">
      <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 class="h6 mb-0">Upcoming Pet Care</h2><a href="{{ route('notifications.index') }}" class="small">View Notifications</a></div>
      <p class="record-meta">Schedules due within 30 days and overdue care.</p>
      <div class="row g-3">
        @forelse ($careSchedules as $schedule)
          <div class="col-12 col-md-6"><div class="border rounded p-3 h-100">
            <h3 class="h6"><a href="{{ route('pets.show', $schedule['pet']) }}">{{ $schedule['pet']->name }}</a> &middot; {{ ucfirst($schedule['type']) }}</h3>
            @include('notifications.schedule', ['schedule' => $schedule])
          </div></div>
        @empty
          <p class="text-muted mb-0">No upcoming vaccination or deworming reminders.</p>
        @endforelse
      </div>
    </section>
    <div class="card card-light p-4">
      <h2 class="h6 mb-2">Your Pets</h2>
      @if ($pets->isEmpty())
        <div class="text-center py-5">
          <p class="text-muted mb-3">No pets registered yet.</p>
          <p class="record-meta">Pets are registered by the Office of the City Veterinarian (OCV) staff on your behalf.</p>
        </div>
      @else
        <p class="record-meta mb-3">{{ $pets->count() }} {{ Str::plural('pet', $pets->count()) }} registered to your account.</p>
        <div class="row g-3">
          @foreach ($pets as $pet)
            <div class="col-12 col-md-6 col-lg-4">
              <a href="/pets/{{ $pet->id }}" class="text-decoration-none text-body">
                <div class="card card-light p-3 h-100">
                  <div class="d-flex align-items-center">
                    <span class="user-chip"><i class="bi bi-heart"></i></span>
                    <div class="ms-3 text-truncate" style="min-width:0">
                      <div class="fw-semibold">{{ $pet->name }}</div>
                      <div class="record-meta">{{ $pet->breed ?? 'Not specified' }} · {{ $pet->sex }}</div>

                    </div>
                  </div>
                </div>
              </a>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
@endsection
