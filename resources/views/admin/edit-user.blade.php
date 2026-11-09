@extends('layouts.app')
@php
  if (! isset($errors)) {
      $errors = new \Illuminate\Support\ViewErrorBag;
  }
@endphp
@section('title', 'Office of the City Veterinarian | Edit User')
@section('content')
 <div class="container-fluid">
    <a href="{{ route('admin.users') }}" class="small text-decoration-none">&larr; Back to User Management</a>
    <div class="card card-light p-4 mt-3">
      <h1 class="h5 mb-1">Edit User</h1>
      <p class="text-muted mb-4">Update account information for <strong>{{ $editUser->name }}</strong>. Existing passwords are never displayed.</p>
      <form method="POST" action="{{ route('admin.users.update', $editUser) }}">
        @csrf
        @method('PUT')
        <div class="border-bottom pb-3 mb-4">
          <div class="page-kicker mb-1">Personal information</div><h2 class="h6 mb-3">Profile details</h2>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Full name *</label><input id="name" name="name" value="{{ old('name', $editUser->name) }}" class="form-control @error('name') is-invalid @enderror" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="contact_number">Contact number *</label><input id="contact_number" name="contact_number" value="{{ old('contact_number', $editUser->contact_number) }}" class="form-control @error('contact_number') is-invalid @enderror" required>@error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="date_of_birth">Date of birth *</label><input id="date_of_birth" name="date_of_birth" type="date" max="{{ now()->toDateString() }}" value="{{ old('date_of_birth', $editUser->date_of_birth?->format('Y-m-d')) }}" class="form-control @error('date_of_birth') is-invalid @enderror" required>@error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          </div>
        </div>
        <div class="border-bottom pb-3 mb-4">
          <div class="page-kicker mb-1">Account email</div><h2 class="h6 mb-3">Login email address</h2>
          <div class="col-md-6"><label class="form-label" for="email">Email address *</label><input id="email" name="email" type="email" value="{{ old('email', $editUser->email) }}" class="form-control @error('email') is-invalid @enderror" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
          <div class="form-text">This address must be valid and unique.</div>
        </div>
        <div class="border-bottom pb-3 mb-4">
          <div class="page-kicker mb-1">Password reset/change</div><h2 class="h6 mb-3">Set a new password</h2>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="password">New password</label><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep the current password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="8" autocomplete="new-password" placeholder="Re-enter the new password"></div>
          </div>
          <div class="form-text">Leave both password fields blank to save profile changes without changing the password.</div>
        </div>
        <div class="mb-4">
          <div class="page-kicker mb-1">Role assignment</div><h2 class="h6 mb-3">System role</h2>
          <div class="col-md-6"><label class="form-label" for="role">Role *</label><select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>@foreach (['user' => 'User / Pet Owner', 'staff' => 'OCV Staff', 'admin' => 'Admin'] as $value => $label)<option value="{{ $value }}" {{ old('role', $editUser->role) === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select>@error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><i class="bi bi-check-lg me-1"></i>Save Changes</button><a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">Cancel</a></div>
      </form>
    </div>
 </div>
@endsection
