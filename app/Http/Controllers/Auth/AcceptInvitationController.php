<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AcceptUserInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AcceptInvitationController extends Controller
{
    public function show(Request $request, UserInvitation $invitation): View
    {
        $plainToken = (string) $request->query('token', '');

        if (! $request->hasValidSignature() || ! $invitation->matchesPlainToken($plainToken)) {
            abort(403, __('auth.invitation_invalid'));
        }

        if ($invitation->isUsed()) {
            return view('auth.accept-invitation', [
                'invitation' => $invitation,
                'statusError' => __('auth.invitation_used'),
                'actionUrl' => $request->fullUrl(),
            ]);
        }

        if ($invitation->isExpired()) {
            return view('auth.accept-invitation', [
                'invitation' => $invitation,
                'statusError' => __('auth.invitation_invalid'),
                'actionUrl' => $request->fullUrl(),
            ]);
        }

        return view('auth.accept-invitation', [
            'invitation' => $invitation->load('user'),
            'statusError' => null,
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(
        AcceptInvitationRequest $request,
        UserInvitation $invitation,
        AcceptUserInvitationAction $acceptAction,
    ): RedirectResponse {
        $plainToken = (string) $request->query('token', '');

        if (! $request->hasValidSignature()) {
            abort(403, __('auth.invitation_invalid'));
        }

        $user = $acceptAction->execute(
            $invitation,
            $plainToken,
            (string) $request->validated('password'),
        );

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()
            ->route('dashboard')
            ->with('status', __('auth.invitation_activated'));
    }
}
