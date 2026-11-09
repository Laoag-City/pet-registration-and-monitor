@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | Admin Dashboard')
@section('content')
  <div class="container-fluid px-0">
    <div class="row mb-4 g-3">
      <div class="col">
        <div class="page-kicker">Administration</div>
        <h1 class="page-title mb-1">Welcome back, {{ $user->name }}</h1>
        <p class="text-muted mb-0">System management overview.</p>
      </div>
      <div class="col-12 col-md-auto d-flex align-items-end gap-2">
        <a href="{{ route('admin.users') }}" class="btn btn-outline-secondary btn-sm"><i
            class="bi bi-people me-1"></i>User Management</a>
        <a href="{{ route('admin.users.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-person-plus me-1"></i>Add
          User</a>
      </div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card card-light p-3">
          <div class="metric-label">Clients</div>
          <div class="metric-value mt-1">{{ $userCount }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-light p-3">
          <div class="metric-label">Staff &amp; Admins</div>
          <div class="metric-value mt-1">{{ $staffCount }}</div>
        </div>
      </div>
      <div class="col-6 col-md-3"><a href="{{ route('staff.pets') }}" class="text-decoration-none">
          <div class="card card-light p-3">
            <div class="metric-label">Registered Pets</div>
            <div class="metric-value mt-1">{{ $petCount }}</div>
          </div>
        </a></div>
      <div class="col-6 col-md-3"><a href="{{ url('/vaccinations') }}" class="text-decoration-none">
          <div class="card card-light p-3">
            <div class="metric-label">Vaccination Records</div>
            <div class="metric-value mt-1">{{ \App\Models\Vaccination::count() }}</div>
          </div>
        </a></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card card-light p-4 h-100">
          <h2 class="h6 mb-3">Recent user registrations</h2>
          @if ($recentUsers->isEmpty())
            <p class="text-muted mb-0">No users registered yet.</p>
          @else
            <ul class="list-unstyled mb-0">
              @foreach ($recentUsers as $account)
                <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                  <span><span class="record-name">{{ $account->name }}</span>
                    <div class="record-meta">{{ $account->email }} &middot; {{ ucfirst($account->role) }}</div>
                  </span>
                  <a href="{{ route('admin.users.edit', $account) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card card-light p-4 h-100">
          <h2 class="h6 mb-3">Recent pet registrations</h2>
          @if ($recentPets->isEmpty())
            <p class="text-muted mb-0">No pets registered yet.</p>
          @else
            <ul class="list-unstyled mb-0">
              @foreach ($recentPets as $pet)
                <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                  <span><span class="record-name">{{ $pet->name }}</span>
                    <div class="record-meta">{{ $pet->owner_name ?? 'No owner' }} &middot; {{ $pet->species }}</div>
                  </span>
                  <a href="{{ route('pets.show', $pet) }}" class="btn btn-outline-secondary btn-sm">View</a>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection