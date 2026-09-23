@extends('layouts.public')
@section('title', 'PawID | Pet Identification')
@section('content')
    <section class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5">
                    <h1 class="h3 mb-4">Pet Identification</h1>
                    <dl class="mb-0">
                        <dt class="small text-muted mb-1">Owner</dt>
                        <dd class="h4 text-break mb-4">{{ $ownerName }}</dd>
                        <dt class="small text-muted mb-1">Pet</dt>
                        <dd class="h4 text-break mb-0">{{ $petName }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </section>
@endsection
