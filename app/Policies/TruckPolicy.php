<?php

namespace App\Policies;

use App\Models\Truck;
use App\Models\User;

class TruckPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Truck $truck): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $truck->driver_id === $user->id;
    }

    /**
     * Criar caminhão — admin/manager ou motorista cadastrando o próprio.
     */
    public function create(User $user): bool
    {
        return true; // Motorista pode cadastrar seu caminhão
    }

    public function update(User $user, Truck $truck): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $truck->driver_id === $user->id;
    }

    public function delete(User $user, Truck $truck): bool
    {
        return $user->isAdmin();
    }
}
