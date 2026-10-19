@extends('layouts.app')
@section('title', 'PawID | User Management')
@section('content')
  <div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-end gap-3 mb-4">
      <div>
        <div class="page-kicker">OCV Staff</div>
        <h1 class="page-title mb-1">User Management</h1>
        <p class="text-muted mb-0">Search for registered clients.</p>
      </div>
    </div>
    <form class="row g-2 mb-4" method="GET" action="{{ route('staff.users') }}">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="{{ $search }}" class="form-control"
            placeholder="Search name, email, or contact number...">
          <button class="btn btn-brand" type="submit">Search</button>
        </div>
      </div>
      @if ($search !== '')
        <div class="col-auto"><a href="{{ route('staff.users') }}" class="btn btn-outline-secondary">Clear</a></div>
      @endif
    </form>
    <section class="card card-light overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Contact</th>
              <th>Registered</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($users as $client)
              <tr>
                <td class="record-name">{{ $client->name }}</td>
                <td>{{ $client->email }}</td>
                <td>{{ $client->contact_number ?? 'Not provided' }}</td>
                <td>{{ $client->created_at->format('M j, Y') }}</td>
                <td class="text-end"><a href="{{ route('staff.users.show', $client) }}" class="btn btn-brand btn-sm">View
                    Details</a></td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted py-5">No clients
                  found{{ $search !== '' ? ' for "' . $search . '"' : '' }}.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <div class="mt-3">{{ $users->links() }}</div>
  </div>
@endsection