@extends('layouts.public')

@section('title', 'Office of the City Veterinarian | Sign In')

@section('content')
    <section class="auth-shell">
        <div class="auth-card auth-card--single">
            <div class="auth-form-shell">
                <h2>Log in</h2>
                <p>Access your pet care records and manage upcoming visits.</p>

                <form method="POST" action="{{ route('login.store') }}" class="form-grid">
                    @csrf

                    @if (session('status'))
                        <div class="alert alert-success py-2 mb-0" role="alert">
                            <i class="bi bi-check-circle-fill me-1"></i>{{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $errors->first() }}
                        </div>
                    @endif

                    <div class="field-group">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" required />
                    </div>

                    <div class="field-group">
                        <label for="password">Password</label>
                        <div class="password-wrap">
                            <input id="password" name="password" type="password" placeholder="Enter your password" required />
                            <button type="button" class="password-toggle" data-password-toggle="#password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="checkbox-wrap">
                            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                            <span>Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">Sign In</button>
                </form>

                <p class="auth-extra">
                    Need an account? <a href="{{ route('register') }}">Create one</a>
                </p>
            </div>
        </div>
    </section>
@endsection
