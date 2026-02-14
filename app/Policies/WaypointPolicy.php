<?php

namespace App\Policies;

use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\User;
use App\Models\Waypoint;

class WaypointPolicy
{
    /**
     * Listar waypoints de um frete — quem pode ver o frete, pode ver os waypoints.
     */
    public function viewAny(User $user, Freight $freight): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $freight->created_by === $user->id;
        }

        return $freight->driver_id === $user->id;
    }

    /**
     * Criar waypoint:
     * - Manager/Admin podem criar em fretes que não estão completos/cancelados
     * - Driver pode criar se enforce_route = false e frete está atribuído a ele
     */
    public function create(User $user, Freight $freight): bool
    {
        if (in_array($freight->status, [FreightStatus::Completed, FreightStatus::Cancelled])) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $freight->created_by === $user->id;
        }

        // Driver pode adicionar waypoints se enforce_route = false
        if ($user->isDriver()) {
            return $freight->driver_id === $user->id && ! $freight->enforce_route;
        }

        return false;
    }

    /**
     * Atualizar waypoint — só quem criou ou admin.
     */
    public function update(User $user, Waypoint $waypoint): bool
    {
        if (in_array($waypoint->freight->status, [FreightStatus::Completed, FreightStatus::Cancelled])) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $waypoint->created_by === $user->id;
    }

    /**
     * Deletar waypoint — só quem criou ou admin.
     */
    public function delete(User $user, Waypoint $waypoint): bool
    {
        if (in_array($waypoint->freight->status, [FreightStatus::Completed, FreightStatus::Cancelled])) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $waypoint->created_by === $user->id;
    }

    /**
     * Registrar chegada/saída — apenas o motorista atribuído ao frete.
     */
    public function checkin(User $user, Waypoint $waypoint): bool
    {
        return $user->isDriver()
            && $waypoint->freight->driver_id === $user->id
            && $waypoint->freight->status === FreightStatus::InTransit;
    }
}
