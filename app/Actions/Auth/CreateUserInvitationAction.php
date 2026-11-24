<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class CreateUserInvitationAction
{
    /**
     * Membuat tautan undangan aktivasi bertanda tangan (signed) yang hanya menyimpan hash token.
     *
     * @return array{invitation: UserInvitation, url: string, expires_at: CarbonImmutable}
     */
    public function execute(User $targetUser, ?User $invitedBy = null): array
    {
        $plainToken = Str::random(64);
        $expiresAt = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'))
            ->addHours((int) config('attendance.invitation_expiry_hours', 72));

        $invitation = DB::transaction(function () use ($targetUser, $invitedBy, $plainToken, $expiresAt): UserInvitation {
            UserInvitation::query()
                ->where('user_id', $targetUser->id)
                ->whereNull('used_at')
                ->update(['expires_at' => CarbonImmutable::now()->subSecond()]);

            return UserInvitation::create([
                'user_id' => $targetUser->id,
                'invited_by' => $invitedBy?->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresAt,
            ]);
        });

        $signedUrl = URL::temporarySignedRoute(
            'invitations.show',
            $expiresAt,
            [
                'invitation' => $invitation->id,
                'token' => $plainToken,
            ],
        );

        return [
            'invitation' => $invitation,
            'url' => $signedUrl,
            'expires_at' => $expiresAt,
        ];
    }
}
