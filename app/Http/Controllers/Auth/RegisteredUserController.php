<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'barangay' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\LaoagBarangays::labels())],
            'contact_number' => ['required', 'string', 'max:40'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validated);

        return redirect()
            ->route('login')
            ->with('status', 'Account created successfully. Please log in.');
    }
}
