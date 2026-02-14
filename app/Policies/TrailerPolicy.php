<?php

namespace App\Policies;

use App\Models\Trailer;
use App\Models\User;

class TrailerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trailer $trailer): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $trailer->driver_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Trailer $trailer): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $trailer->driver_id === $user->id;
    }

    public function delete(User $user, Trailer $trailer): bool
    {
        return $user->isAdmin();
    }
}
