<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
