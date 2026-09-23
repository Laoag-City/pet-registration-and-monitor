@extends('layouts.app')
@section('title', 'PawID | Register Pet')
@section('content')
 <div class="container-fluid">
    <h1 class="page-title mb-1">Register Pet</h1>
    <p class="text-muted mb-4">Register a pet on behalf of an existing client.</p>

    <div class="card card-light p-4 mb-4">
      <h2 class="h6 mb-3">1. Select client</h2>
      <form method="GET" action="{{ route('staff.pets.create') }}" class="row g-2">
        <div class="col-12 col-md-6 col-lg-4">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search clients by name or email...">
            <button class="btn btn-brand" type="submit">Search</button>
          </div>
        </div>
      </form>
      @if ($selectedClient)
        <div class="alert alert-success mt-3 mb-0 d-flex justify-content-between align-items-center">
          <span><i class="bi bi-check-circle-fill me-2"></i>Selected client: <strong>{{ $selectedClient->name }}</strong> ({{ $selectedClient->email }})</span>
          <a href="{{ route('staff.pets.create') }}" class="btn btn-outline-secondary btn-sm">Change</a>
        </div>
      @elseif ($clients->isNotEmpty())
        <div class="table-responsive mt-3">
          <table class="table mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Contact</th><th></th></tr></thead>
            <tbody>
              @foreach ($clients as $client)
                <tr>
                  <td class="record-name">{{ $client->name }}</td>
                  <td>{{ $client->email }}</td>
                  <td>{{ $client->contact_number ?? 'Not provided' }}</td>
                  <td class="text-end"><a href="{{ route('staff.pets.create', ['client' => $client->id, 'q' => $search]) }}" class="btn btn-brand btn-sm">Select</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mt-3 mb-0">No clients found{{ $search !== '' ? ' for "' . $search . '"' : '' }}.</p>
      @endif
    </div>

    @if ($selectedClient)
      <div class="card card-light p-4">
        <h2 class="h6 mb-3">2. Pet information</h2>
        <form method="POST" action="{{ route('staff.pets.store') }}" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="owner_id" value="{{ $selectedClient->id }}">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Pet name *</label><input name="name" value="{{ old('name') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Species *</label><input name="species" value="{{ old('species') }}" class="form-control" placeholder="Dog, Cat, ..." required></div>
            <div class="col-md-6"><label class="form-label">Breed</label><input name="breed" value="{{ old('breed') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Sex *</label>
              <select name="sex" class="form-select" required>
                <option value="">Select sex</option>
                @foreach (['Male', 'Female'] as $sex)
                  <option value="{{ $sex }}" {{ old('sex') === $sex ? 'selected' : '' }}>{{ $sex }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Date of birth</label><input name="date_of_birth" type="date" max="{{ now()->toDateString() }}" value="{{ old('date_of_birth') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Color</label><input name="color" value="{{ old('color') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Photo</label><input name="photo" type="file" accept="image/*" class="form-control"></div>
          </div>
          <div class="mt-4"><button class="btn btn-brand" type="submit"><i class="bi bi-check-lg me-1"></i>Register Pet</button></div>
        </form>
      </div>
    @endif
 </div>
@endsection
