<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PhotographerProfile;
use App\Models\User;

class PhotographerProfilePolicy
{
    public function view(User $user, PhotographerProfile $profile): bool
    {
        return $user->isAdmin() || $profile->user_id === $user->id;
    }

    public function update(User $user, PhotographerProfile $profile): bool
    {
        return $user->isAdmin() || $profile->user_id === $user->id;
    }

    public function delete(User $user, PhotographerProfile $profile): bool
    {
        return $user->isAdmin();
    }
}
