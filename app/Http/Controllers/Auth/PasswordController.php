<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the signed-in admin or staff password.
     */
    public function update(Request $request): RedirectResponse
    {
        $guard = Auth::guard('admin')->check() ? 'admin' : Auth::getDefaultDriver();
        $user = $request->user($guard) ?? $request->user();

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password:'.$guard],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('profile.edit', ['tab' => 'password'])
            ->with('status', 'password-updated');
    }
}
