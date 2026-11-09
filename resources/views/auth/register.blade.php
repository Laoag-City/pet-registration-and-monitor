@extends('layouts.public')

@section('title', 'Office of the City Veterinarian | Create Account')

@section('content')
    <section class="auth-shell">
        <div class="auth-card auth-card--single">
            <div class="auth-form-shell">
                <h2>Create account</h2>
                <p>Register with the Office of the City Veterinarian to keep all your pet records organized and easy to share.</p>

                <form method="POST" action="{{ route('register.store') }}" class="form-grid">
                    @csrf

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $errors->first() }}
                        </div>
                    @endif

                    <div class="field-group">
                        <label for="full_name">Full name</label>
                        <input id="full_name" name="name" type="text" value="{{ old('name') }}" placeholder="Jordan Smith" required />
                    </div>

                    <div class="field-group">
                        <label for="register_email">Email address</label>
                        <input id="register_email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" required />
                    </div>

                    <div class="field-group">
                        <label for="contact_number">Contact number</label>
                        <input id="contact_number" name="contact_number" type="tel" value="{{ old('contact_number') }}" placeholder="09XX XXX XXXX" required />
                    </div>

                    <div class="field-group">
                        <label for="date_of_birth">Date of birth</label>
                        <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}" required />
                        <small class="field-hint">Must not be a future date.</small>
                    </div>

                    <div class="field-group">
                        <label for="register_password">Password</label>
                        <div class="password-wrap">
                            <input id="register_password" name="password" type="password" placeholder="Create a password (min. 8 characters)" required />
                            <button type="button" class="password-toggle" data-password-toggle="#register_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="confirm_password">Confirm password</label>
                        <div class="password-wrap">
                            <input id="confirm_password" name="password_confirmation" type="password" placeholder="Re-enter your password" required />
                            <button type="button" class="password-toggle" data-password-toggle="#confirm_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">Create Account</button>
                </form>

                <p class="auth-extra">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                </p>
            </div>
        </div>
    </section>
@endsection
