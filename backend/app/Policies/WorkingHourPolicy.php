<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkingHour;

class WorkingHourPolicy
{
    public function view(User $user, WorkingHour $workingHour): bool
    {
        return $user->isAdmin() || $workingHour->photographer_id === $user->id;
    }

    public function update(User $user, WorkingHour $workingHour): bool
    {
        return $user->isAdmin() || $workingHour->photographer_id === $user->id;
    }

    public function delete(User $user, WorkingHour $workingHour): bool
    {
        return $user->isAdmin() || $workingHour->photographer_id === $user->id;
    }
}
