<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function view(User $user, Package $package): bool
    {
        return $user->isAdmin() || $package->photographer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isPhotographer() || $user->isAdmin();
    }

    public function update(User $user, Package $package): bool
    {
        return $user->isAdmin() || $package->photographer_id === $user->id;
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->isAdmin() || $package->photographer_id === $user->id;
    }
}
