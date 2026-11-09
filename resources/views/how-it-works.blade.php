@extends('layouts.public')

@section('title', 'Office of the City Veterinarian | How It Works')

@section('content')
    <section class="page-section" id="how-it-works">
        <div class="container">
            <div class="section-heading section-heading--center">
                <span class="eyebrow">How It Works</span>
                <h2>How it works</h2>
                <p>From registration to a scannable tag, your pet is protected in four simple steps.</p>
            </div>
            <div class="row g-4">
                @foreach ([
                        ['01', 'Register', 'Create your account, then ask OCV staff to register your pet\'s details and photo.'],
                        ['02', 'Add Records', 'OCV staff record vaccinations and deworming treatments for your pets.'],
                        ['03', 'Get Your Pet QR', 'Generate your pet\'s unique QR code and attach it to their collar tag.'],
                        ['04', 'Scan & Access', 'New QR codes show the owner and pet names. Sign in as the owner or authorized staff to access private records.'],
                    ] as $step)
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="step-item h-100">
                            <div class="step-index">{{ $step[0] }}</div>
                            <h3>{{ $step[1] }}</h3>
                            <p>{{ $step[2] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="page-actions">
                <a href="{{ route('register') }}" class="btn btn-primary">Get Started</a>
                <a href="{{ route('features') }}" class="btn btn-secondary">View Features</a>
            </div>
        </div>
    </section>
@endsection
