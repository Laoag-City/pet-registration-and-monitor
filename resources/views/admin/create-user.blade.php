@extends('layouts.app')
@section('title', 'PawID | Add User')
@section('content')
  <div class="container-fluid">
    <a href="{{ route('admin.users') }}" class="small text-decoration-none">&larr; Back to User Management</a>
    <div class="card card-light p-4 mt-3">
      <h1 class="h5 mb-3">Add User</h1>
      <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6"><label class="form-label">Full name *</label><input name="name" value="{{ old('name') }}"
            class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Email *</label><input name="email" type="email"
            value="{{ old('email') }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Contact number *</label><input name="contact_number"
            value="{{ old('contact_number') }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Date of birth *</label><input name="date_of_birth" type="date"
            max="{{ now()->toDateString() }}" value="{{ old('date_of_birth') }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Password *</label><input name="password" type="password"
            class="form-control" minlength="8" required></div>
        <div class="col-md-6"><label class="form-label">Role *</label>
          <select name="role" class="form-select" required>
            <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>User / Pet Owner</option>
            <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>OCV Staff</option>
            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
          </select>
        </div>
        <div class="col-12"><button class="btn btn-brand" type="submit"><i class="bi bi-check-lg me-1"></i>Create
            User</button></div>
      </form>
    </div>
  </div>
@endsection