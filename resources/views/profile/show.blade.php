@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | My Profile')
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><div class="page-kicker">My account</div><h1 class="page-title mb-0">My Profile</h1></div>
            <a href="{{ route('profile.edit') }}" class="btn btn-brand"><i class="bi bi-pencil me-1"></i>Edit Profile</a>
        </div>
        <section class="card card-light p-3 p-md-4">
            <h2 class="h5 mb-4">Personal Information</h2>
            <dl class="row g-3 mb-0">
                <div class="col-md-6"><dt class="record-meta mb-1">Full Name</dt><dd class="fw-semibold text-break mb-0">{{ $profile->name }}</dd></div>
                <div class="col-md-6"><dt class="record-meta mb-1">Email Address</dt><dd class="fw-semibold text-break mb-0">{{ $profile->email }}</dd></div>
                <div class="col-md-6"><dt class="record-meta mb-1">Contact Number</dt><dd class="fw-semibold text-break mb-0">{{ $profile->contact_number ?: 'Not provided' }}</dd></div>
                <div class="col-md-6"><dt class="record-meta mb-1">Date of Birth</dt><dd class="fw-semibold mb-0">{{ $profile->date_of_birth?->format('M j, Y') ?? 'Not provided' }}</dd></div>
            </dl>
        </section>
    </div>
@endsection
