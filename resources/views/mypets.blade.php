@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h4 mb-1">My Pets</h1><div class="small text-muted">{{ $pets->count() }} {{ Str::plural('pet', $pets->count()) }} registered</div></div></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($pets->isEmpty())
      <div class="text-center p-5 card card-light"><p class="mb-0 text-muted">No pets registered yet.</p></div>
    @else
      <div class="row g-3">
        @foreach ($pets as $pet)
          <div class="col-12 col-md-6 col-lg-4"><a href="/pets/{{ $pet->id }}" class="text-decoration-none text-body"><div class="card card-light p-3 h-100"><div class="d-flex align-items-center"><div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="bi bi-heart"></i></div><div class="ms-3"><div class="fw-semibold">{{ $pet->name }}</div><div class="small text-muted">{{ $pet->breed ?? 'Not specified' }} · {{ $pet->sex }}</div></div></div></div></a></div>
        @endforeach
      </div>
    @endif
  </div>
@endsection
