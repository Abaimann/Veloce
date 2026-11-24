<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->validated('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user !== null && ! $user->hasActivatedPassword()) {
            throw ValidationException::withMessages([
                'email' => __('auth.not_activated'),
            ]);
        }

        if ($user !== null && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('auth.inactive'),
            ]);
        }

        $credentials = [
            'email' => $user?->email ?? $email,
            'password' => $request->validated('password'),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $authenticatedUser = $request->user();
        $authenticatedUser?->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', __('auth.logged_out'));
    }
}
