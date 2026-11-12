@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | Client Details')
@section('content')
 <div class="container-fluid">
    <a href="{{ route('staff.users') }}" class="small text-decoration-none">&larr; Back to User Management</a>
    <div class="card card-light p-4 mt-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <h1 class="h4 mb-0">Owner Information</h1>
        <a href="{{ route('staff.pets.create') }}?client={{ $client->id }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>Register Pet for this Client</a>
      </div>
      <div class="row g-3">
        <div class="col-6 col-md-3"><div class="small text-muted">Full name</div><div>{{ $client->name }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Email</div><div>{{ $client->email }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Contact number</div><div>{{ $client->contact_number ?? 'Not provided' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Date of birth</div><div>{{ $client->date_of_birth?->format('M d, Y') ?? 'Not provided' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Address / Barangay (Laoag City)</div><div>{{ $client->barangay ?? 'Not Provided' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Registered</div><div>{{ $client->created_at->format('M d, Y') }}</div></div>
      </div>
    </div>
    <div class="card card-light p-4 mt-3">
      <h2 class="h6 mb-3">Registered Pets ({{ $pets->count() }})</h2>
      @if ($pets->isEmpty())
        <p class="text-muted mb-0">This client has no registered pets.</p>
      @else
        <div class="row g-3">
          @foreach ($pets as $pet)
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card card-light p-3 h-100">
                <div class="d-flex align-items-center mb-2">
                  @if ($pet->photo_path)
                    <img src="{{ asset('storage/'.$pet->photo_path) }}" alt="Photo of {{ $pet->name }}" class="rounded" style="width:48px;height:48px;object-fit:cover;">
                  @else
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="bi bi-heart"></i></div>
                  @endif
                  <div class="ms-3" style="min-width:0">
                    <div class="fw-semibold text-truncate">{{ $pet->name }}</div>
                    <div class="record-meta">{{ $pet->species }} &middot; {{ $pet->sex }}</div>
                  </div>
                </div>
                <div class="record-meta mb-2">
                  Receipt Number: {{ $pet->receipt_number ?? 'Not Provided' }}<br>
                  DOB: {{ $pet->date_of_birth?->format('M d, Y') ?? 'Not specified' }}<br>
                  Vaccinations: {{ $pet->vaccinations->count() }} &middot; Deworming: {{ $pet->dewormingRecords->count() }}
                </div>
                <a href="{{ route('pets.show', $pet) }}" class="btn btn-brand btn-sm">View Pet Details</a>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
 </div>
@endsection
