<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptUserInvitationAction
{
    /**
     * Memvalidasi undangan bertanda tangan dan menyimpan kata sandi baru pilihan karyawan.
     */
    public function execute(UserInvitation $invitation, string $plainToken, string $plainPassword): User
    {
        if ($invitation->isUsed()) {
            throw ValidationException::withMessages([
                'token' => __('auth.invitation_used'),
            ]);
        }

        if ($invitation->isExpired() || ! $invitation->matchesPlainToken($plainToken)) {
            throw ValidationException::withMessages([
                'token' => __('auth.invitation_invalid'),
            ]);
        }

        return DB::transaction(function () use ($invitation, $plainPassword): User {
            $lockedInvitation = UserInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->isUsed() || $lockedInvitation->isExpired()) {
                throw ValidationException::withMessages([
                    'token' => __('auth.invitation_used'),
                ]);
            }

            $now = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'));
            $user = $lockedInvitation->user;

            $user->forceFill([
                'password' => $plainPassword,
                'email_verified_at' => $user->email_verified_at ?? $now,
                'is_active' => true,
            ])->save();

            $lockedInvitation->forceFill([
                'used_at' => $now,
            ])->save();

            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->log('Mengaktifkan akun melalui tautan undangan');

            return $user;
        });
    }
}
