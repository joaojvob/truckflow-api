<?php

namespace App\Policies;

use App\Models\DriverProfile;
use App\Models\User;

class DriverProfilePolicy
{
    public function uploadCnh(User $user): bool
    {
        return $user->isDriver();
    }

    public function viewCnh(User $user, DriverProfile $profile): bool
    {
        if ($user->id === $profile->user_id) {
            return true;
        }

        if ($user->tenant_id !== $profile->tenant_id) {
            return false;
        }

        return $user->isAdmin() || $user->isManager();
    }
}
