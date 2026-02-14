<?php

namespace App\Policies;

use App\Models\Freight;
use App\Models\User;

class FreightPolicy
{
    /**
     * Listar fretes — qualquer autenticado do tenant.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver detalhes — motorista só vê o dele, gestor/admin vê todos do tenant.
     */
    public function view(User $user, Freight $freight): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $freight->driver_id === $user->id;
    }

    /**
     * Criar frete — apenas admin e manager.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Atualizar frete — apenas admin e manager.
     */
    public function update(User $user, Freight $freight): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Deletar frete — apenas admin.
     */
    public function delete(User $user, Freight $freight): bool
    {
        return $user->isAdmin();
    }

    /**
     * Iniciar viagem — apenas o motorista alocado ao frete.
     */
    public function start(User $user, Freight $freight): bool
    {
        return $user->isDriver() && $freight->driver_id === $user->id;
    }

    /**
     * Finalizar viagem — apenas o motorista alocado ao frete.
     */
    public function complete(User $user, Freight $freight): bool
    {
        return $user->isDriver() && $freight->driver_id === $user->id;
    }
}
