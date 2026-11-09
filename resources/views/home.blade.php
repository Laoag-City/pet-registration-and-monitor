@extends('layouts.public')

@section('title', 'Office of the City Veterinarian | QR-Based Pet Registration and Medical Record System')

@section('content')
    {{-- Hero --}}
    <section class="hero-section">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow">QR-Based Pet Registration &amp; Medical Records</span>
                    <h1>Your pet's identity, always within reach.</h1>
                    <p>Register your pet, keep their medical records organized, and give them a digital identity through the Office of the City Veterinarian.</p>
                    <div class="hero-actions">
                        <a href="{{ route('register') }}" class="btn btn-primary btn-primary--large">
                            <i class="bi bi-qr-code me-2"></i>Create Account
                        </a>
                        <a href="{{ route('login') }}" class="btn btn-secondary btn-primary--large">Log In</a>
                    </div>

                    <ul class="hero-links">
                        <li><a href="{{ route('features') }}"><i class="bi bi-star me-2"></i>Features</a></li>
                        <li><a href="{{ route('how-it-works') }}"><i class="bi bi-arrow-repeat me-2"></i>How It Works</a></li>
                        <li><a href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
@endsection
