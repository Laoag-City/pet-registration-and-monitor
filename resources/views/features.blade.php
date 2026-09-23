@extends('layouts.public')

@section('title', 'PawID | Features')

@section('content')
    <section class="page-section" id="features">
        <div class="container">
            <div class="section-heading section-heading--center">
                <span class="eyebrow">Features</span>
                <h2>Everything your pet needs, in one profile</h2>
                <p>PawID keeps every record organized so veterinarians and finders can help your pet faster.</p>
            </div>
            <div class="row g-4">
                @foreach ([
                        ['bi-person-vcard', 'Pet Registration', 'Create a complete identity for your pet with details, photos, and a unique pet ID.'],
                        ['bi-id-card', 'Digital Pet Profile', 'Access your pet\'s full profile anytime from any device, no paperwork required.'],
                        ['bi-shield-plus', 'Vaccination Records', 'Track vaccine schedules and keep shot histories current and easy to share.'],
                        ['bi-capsule', 'Deworming Records', 'Log every deworming treatment so you never miss the next dose.'],
                        ['bi-clipboard2-pulse', 'Medical Records', 'Store diagnoses, treatments, and vet notes in one secure timeline.'],
                        ['bi-qr-code-scan', 'QR Code Identification', 'Scan a new pet QR tag to view the owner and pet names. Private records remain protected.'],
                    ] as $feature)
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="feature-card h-100">
                            <div class="feature-icon"><i class="bi {{ $feature[0] }}"></i></div>
                            <h3>{{ $feature[1] }}</h3>
                            <p>{{ $feature[2] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="page-actions">
                <a href="{{ route('register') }}" class="btn btn-primary">Create Account</a>
                <a href="{{ route('how-it-works') }}" class="btn btn-secondary">See How It Works</a>
            </div>
        </div>
    </section>
@endsection
