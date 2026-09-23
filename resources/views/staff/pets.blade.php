@extends('layouts.app')
@section('title', 'PawID | All Pets')
@section('content')
 <div class="container-fluid">
    <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-end gap-3 mb-4">
      <div><div class="page-kicker">OCV Staff</div><h1 class="page-title mb-1">All Pets</h1><p class="text-muted mb-0">Search pets across the entire database.</p></div>
      <a href="{{ route('staff.pets.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>Register Pet</a>
    </div>
    <form class="row g-2 mb-4" method="GET" action="{{ route('staff.pets') }}">
      <div class="col-12 col-md-5 col-lg-4">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search pet name or owner...">
          <button class="btn btn-brand" type="submit">Search</button>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <select name="species" class="form-select" onchange="this.form.submit()">
          <option value="">All species</option>
          @foreach ($speciesList as $s)
            <option value="{{ $s }}" {{ $speciesFilter === $s ? 'selected' : '' }}>{{ $s }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto"><a href="{{ route('staff.pets') }}" class="btn btn-outline-secondary">Clear</a></div>
    </form>
    <section class="card card-light overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Pet</th><th>Species</th><th>Sex</th><th>Owner</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            @forelse ($pets as $pet)
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    @if ($pet->photo_path)
                      <img src="{{ asset('storage/'.$pet->photo_path) }}" class="rounded" style="width:36px;height:36px;object-fit:cover;" alt="">
                    @else
                      <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:36px;height:36px"><i class="bi bi-heart"></i></div>
                    @endif
                    <div class="ms-2"><div class="record-name">{{ $pet->name }}</div><div class="record-meta">{{ $pet->breed ?? '' }}</div></div>
                  </div>
                </td>
                <td>{{ \Illuminate\Support\Str::ucfirst(mb_strtolower(trim($pet->species))) }}</td>
                <td>{{ $pet->sex }}</td>
                <td><div class="record-name">{{ $pet->owner_name ?? 'Unknown' }}</div><div class="record-meta">{{ $pet->owner_email ?? '' }}</div></td>
                <td class="text-end"><div class="d-flex flex-wrap justify-content-end gap-1">
                  <a href="{{ route('pets.show', $pet) }}" class="btn btn-brand btn-sm" aria-label="View {{ $pet->name }}">View</a>
                  <a href="{{ route('staff.pets.edit', $pet) }}" class="btn btn-outline-secondary btn-sm" aria-label="Edit {{ $pet->name }}">Edit</a>
                  <a href="{{ route('staff.pets.remove', $pet) }}" class="btn btn-outline-danger btn-sm" aria-label="Remove {{ $pet->name }}">Remove</a>
                </div></td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-5">No pets found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <div class="mt-3">{{ $pets->links() }}</div>
 </div>
@endsection
