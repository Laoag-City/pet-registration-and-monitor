@extends('layouts.app')
@section('title', 'Office of the City Veterinarian | '.($type === 'vaccinations' ? 'Add Vaccination' : 'Add Deworming'))
@section('content')
    <div class="container-fluid">
        <a href="{{ route($type.'.index') }}" class="small text-decoration-none">&larr; Back to {{ $type === 'vaccinations' ? 'Vaccinations' : 'Deworming' }}</a>
        <h1 class="page-title mt-3 mb-1">Add {{ $type === 'vaccinations' ? 'Vaccination' : 'Deworming' }} Record</h1>
        <p class="text-muted mb-4">Identify the owner, select their pet, then enter and review the treatment.</p>
        @include('medical.steps', ['step' => $owner ? 2 : 1])

        @if (!$owner)
            <section class="card card-light p-3 p-md-4">
                <h2 class="h5 mb-3">Select Owner</h2>
                <form method="GET" action="{{ route($type.'.create') }}" class="row g-2 mb-4">
                    <div class="col-12 col-lg-8">
                        <label for="owner-search" class="visually-hidden">Search owner by name, email, or contact number</label>
                        <div class="input-group">
                            <input id="owner-search" name="q" type="search" value="{{ $search }}" maxlength="255" class="form-control" placeholder="Search owner by name, email, or contact number...">
                            <button class="btn btn-brand" type="submit">Search</button>
                        </div>
                    </div>
                    <div class="col-auto"><a href="{{ route($type.'.create') }}" class="btn btn-outline-secondary">Clear</a></div>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Name</th><th>Email</th><th>Contact number</th><th><span class="visually-hidden">Action</span></th></tr></thead>
                        <tbody>
                            @forelse ($owners as $client)
                                <tr>
                                    <td class="record-name">{{ $client->name }}</td>
                                    <td>{{ $client->email }}</td>
                                    <td>{{ $client->contact_number ?: 'Not provided' }}</td>
                                    <td class="text-end"><a class="btn btn-brand btn-sm text-nowrap" href="{{ route($type.'.create', ['owner_id' => $client->id]) }}">Select Owner<span class="visually-hidden"> {{ $client->name }}</span></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-5">No owners found. Try another name, email, or contact number.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $owners->links() }}</div>
            </section>
        @else
            @include('medical.identity')
            <section class="card card-light p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0">Select Pet</h2>
                    <a href="{{ route($type.'.create') }}" class="btn btn-outline-secondary btn-sm">Change Owner</a>
                </div>
                <div class="row g-3">
                    @forelse ($pets as $pet)
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="border rounded p-3 h-100 d-flex flex-column">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    @if ($pet->photo_path)
                                        <img src="{{ asset('storage/'.$pet->photo_path) }}" alt="Photo of {{ $pet->name }}" class="rounded" style="width:56px;height:56px;object-fit:cover">
                                    @else
                                        <i class="bi bi-heart fs-3" aria-hidden="true"></i>
                                    @endif
                                    <div><div class="record-name">{{ $pet->name }}</div><div class="record-meta">{{ $pet->species }} &middot; {{ $pet->breed ?: 'Breed not specified' }} &middot; {{ $pet->sex ?: 'Sex not specified' }}</div></div>
                                </div>
                                <a class="btn btn-brand btn-sm mt-auto align-self-start" href="{{ route($type.'.create', ['owner_id' => $owner->id, 'pet_id' => $pet->id]) }}">Select Pet<span class="visually-hidden"> {{ $pet->name }}</span></a>
                            </div>
                        </div>
                    @empty
                        <div class="col-12"><p class="text-muted mb-0 py-4">No registered pets found for this owner.</p></div>
                    @endforelse
                </div>
                <div class="mt-3">{{ $pets->links() }}</div>
            </section>
        @endif
    </div>
@endsection
