@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | Edit Profile')
@section('content')
    <div class="container-fluid">
        <a href="{{ route('profile.show') }}" class="small text-decoration-none">&larr; Back to My Profile</a>
        <h1 class="page-title mt-3 mb-4">Edit Profile</h1>
        <section class="card card-light p-3 p-md-4">
            <h2 class="h5 mb-4">Personal Information</h2>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full Name *</label>
                        <input id="name" name="name" autocomplete="name" value="{{ old('name', $profile->name) }}" maxlength="{{ $maximumLength }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address *</label>
                        <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $profile->email) }}" maxlength="{{ $maximumLength }}" class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Use this email address the next time you log in.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input id="contact_number" name="contact_number" type="tel" autocomplete="tel" value="{{ old('contact_number', $profile->contact_number) }}" maxlength="40" class="form-control @error('contact_number') is-invalid @enderror">
                        @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input id="date_of_birth" name="date_of_birth" type="date" autocomplete="bday" value="{{ old('date_of_birth', $profile->date_of_birth?->format('Y-m-d')) }}" max="{{ today()->toDateString() }}" class="form-control @error('date_of_birth') is-invalid @enderror">
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-brand">Save Changes</button>
                    <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </div>
@endsection
