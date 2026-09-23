@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ auth()->user()->canManageRecords() ? url('staff/pets') : url('mypets') }}" class="small text-decoration-none">&larr; Back</a>
      @if (auth()->user()->canManageRecords())
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ route('staff.pets.edit', $pet) }}" class="btn btn-outline-secondary btn-sm">Edit Pet</a>
          <a href="{{ route('staff.pets.remove', $pet) }}" class="btn btn-outline-danger btn-sm">Remove Pet</a>
        </div>
      @endif
    </div>

    <div class="card card-light p-4 mt-3">
      <div class="d-flex align-items-center mb-3">
        @if ($pet->photo_path)
          <img src="{{ asset('storage/'.$pet->photo_path) }}" alt="Photo of {{ $pet->name }}" class="rounded"
            style="width:56px;height:56px;object-fit:cover;">
        @else
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:56px;height:56px"><i class="bi bi-heart"></i></div>
        @endif
        <div class="ms-3">
          <h1 class="h4 mb-0">{{ $pet->name }}</h1>

        </div>
      </div>

      <div class="row g-3">
        <div class="col-6 col-md-3"><div class="small text-muted">Species</div><div>{{ $pet->species ?? 'Not specified' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Breed</div><div>{{ $pet->breed ?? 'Not specified' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Sex</div><div>{{ $pet->sex }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Date of Birth</div><div>{{ $pet->date_of_birth?->format('M d, Y') ?? 'Not specified' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Color</div><div>{{ $pet->color ?? 'Not specified' }}</div></div>
      </div>
    </div>

    <div class="card card-light p-4 mt-3">
      <h2 class="h6 mb-3">Owner information</h2>
      <div class="row g-3">
        <div class="col-6 col-md-3"><div class="small text-muted">Owner name</div><div>{{ $pet->owner_name ?? 'Not specified' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Email</div><div>{{ $pet->owner_email ?? 'Not specified' }}</div></div>
        <div class="col-6 col-md-3"><div class="small text-muted">Contact number</div><div>{{ $pet->owner_number ?? 'Not specified' }}</div></div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-6">
        <div class="card card-light p-3 h-100">
          <h2 class="h6 mb-2">Vaccinations</h2>
          @if ($pet->vaccinations->isEmpty())
            <div class="small text-muted">No vaccination records.</div>
          @else
            <ul class="list-unstyled mb-0 small">
              @foreach ($pet->vaccinations as $record)
                <li class="d-flex justify-content-between border-bottom py-2"><span>{{ $record->vaccine }}</span><span class="text-muted">{{ $record->administered_at->format('M d, Y') }}</span></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
      <div class="col-md-6">
        <div class="card card-light p-3 h-100">
          <h2 class="h6 mb-2">Deworming</h2>
          @if ($pet->dewormingRecords->isEmpty())
            <div class="small text-muted">No deworming records.</div>
          @else
            <ul class="list-unstyled mb-0 small">
              @foreach ($pet->dewormingRecords as $record)
                <li class="d-flex justify-content-between border-bottom py-2"><span>{{ $record->product }}</span><span class="text-muted">{{ $record->administered_at->format('M d, Y') }}</span></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
