@extends('layouts.app')
@section('title', 'PawID | Admin User Management')
@section('content')
  <div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-end gap-3 mb-4">
      <div>
        <div class="page-kicker">Administration</div>
        <h1 class="page-title mb-1">User Management</h1>
        <p class="text-muted mb-0">Manage all system accounts and roles.</p>
      </div>
      <a href="{{ route('admin.users.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-person-plus me-1"></i>Add
        User</a>
    </div>
    <form class="row g-2 mb-4" method="GET" action="{{ route('admin.users') }}">
      <div class="col-12 col-md-5 col-lg-4">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="{{ $search }}" class="form-control"
            placeholder="Search name, email, or contact...">
          <button class="btn btn-brand" type="submit">Search</button>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <select name="role" class="form-select" onchange="this.form.submit()">
          <option value="">All roles</option>
          @foreach (['user' => 'Pet Owner', 'staff' => 'OCV Staff', 'admin' => 'Admin'] as $value => $label)
            <option value="{{ $value }}" {{ $roleFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto"><a href="{{ route('admin.users') }}" class="btn btn-outline-secondary">Clear</a></div>
    </form>
    <section class="card card-light overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Contact</th>
              <th>Role</th>
              <th>Registered</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($users as $account)
              <tr>
                <td class="record-name">{{ $account->name }}</td>
                <td>{{ $account->email }}</td>
                <td>{{ $account->contact_number ?? 'Not provided' }}</td>
                <td><span
                    class="badge {{ $account->isAdmin() ? 'bg-danger' : ($account->isStaff() ? 'bg-primary' : 'bg-secondary') }}">{{ ucfirst($account->role) }}</span>
                </td>
                <td>{{ $account->created_at->format('M j, Y') }}</td>
                <td class="text-end text-nowrap">
                  <a href="{{ route('admin.users.edit', $account) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                  @if (!$account->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.destroy', $account) }}" class="d-inline"
                      onsubmit="return confirm('Delete {{ $account->name }}?')">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-5">No users found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <div class="mt-3">{{ $users->links() }}</div>
  </div>
@endsection