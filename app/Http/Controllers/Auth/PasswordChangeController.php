<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PasswordChangeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('passwordChange', [
            'current_password' => ['required', 'string', 'current_password'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $request->user()->update(['password' => $validated['new_password']]);
        // Invalidates other sessions and remember-me tokens.
        Auth::logoutOtherDevices($validated['new_password']);
        $request->session()->regenerate();
        $request->session()->put('must_change_password', false);

        return back()->with('status', 'Your password has been changed.');
    }
}
