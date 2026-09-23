@extends('layouts.public')

@section('title', 'PawID | Verify Your Email')

@section('content')
    <section class="auth-shell">
        <div class="auth-grid">
            <div class="auth-panel auth-panel--brand">
                <div class="auth-brand">
                    <span class="brand-mark" aria-hidden="true">
                        <img src="{{ asset('images/city vet.jpg') }}" alt="">
                    </span>
                    {{--
                                d="M9.5 7.2c1.8 0 3.2-1.4 3.2-3.2S11.3 0 9.5 0 6.3 1.4 6.3 3.2s1.5 3.2 3.2 3.2Zm13 0c1.8 0 3.2-1.4 3.2-3.2S24.3 0 22.5 0s-3.2 1.4-3.2 3.2 1.5 3.2 3.2 3.2ZM4.6 16.1c1.8 0 3.2-1.4 3.2-3.2s-1.4-3.2-3.2-3.2S1.4 11.1 1.4 13s1.5 3.1 3.2 3.1Zm22.8 0c1.8 0 3.2-1.4 3.2-3.2s-1.4-3.2-3.2-3.2-3.2 1.4-3.2 3.2 1.4 3.2 3.2 3.2ZM16 11.6c2.3 0 4.1 1.9 4.1 4.1v6.6c0 2.3-1.8 4.1-4.1 4.1s-4.1-1.8-4.1-4.1v-6.6c0-2.2 1.8-4.1 4.1-4.1Zm2.1-8.7c1.8 0 3.2-1.4 3.2-3.2S19.9 0 18.1 0s-3.2 1.4-3.2 3.2 1.4 3.2 3.2 3.2Zm-10.6 6.8c1.6 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.4 3 3 3Zm18.9 0c1.6 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.4 3 3 3Z"
                                fill="currentColor" />
                    --}}
                    <span class="brand-text">PawID</span>
                </div>

                <h1>One last step before your dashboard.</h1>
                <p>Verify your email address to activate your PawID account and start registering pets and medical records.
                </p>

                <div class="auth-visual" aria-hidden="true">
                    <div class="paw-illustration">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>

            <div class="auth-panel">
                <div class="auth-form-shell">
                    <h2>Verify your email</h2>
                    <p>A verification link was sent to <strong>{{ auth()->user()->email }}</strong>. Click the link in that
                        email to continue.</p>

                    @if (session('status') === 'verification-link-sent' || session('status'))
                        <div class="alert alert-success py-2" role="alert">
                            <i class="bi bi-envelope-check-fill me-1"></i>
                            @if (session('status') === 'verification-link-sent')
                                A new verification link has been sent to your email address.
                            @else
                                {{ session('status') }}
                            @endif
                        </div>
                    @endif

                    <form method="POST" action="{{ route('verification.send') }}" class="form-grid">
                        @csrf
                        <button type="submit" class="btn btn-primary auth-submit">
                            <i class="bi bi-arrow-repeat me-2"></i>Resend verification email
                        </button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <a href="#" onclick="this.closest('form').submit(); return false;" class="auth-back-link">
                            &larr; Sign out of PawID
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection