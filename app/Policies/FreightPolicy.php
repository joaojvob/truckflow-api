<?php

namespace App\Policies;

use App\Models\Freight;
use App\Models\User;

class FreightPolicy
{
    /**
     * Listar fretes:
     * - Admin vê todos do tenant
     * - Manager vê apenas os que ele criou (vinculados a ele)
     * - Driver vê apenas os atribuídos a ele
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Ver detalhes:
     * - Admin vê qualquer frete do tenant
     * - Manager só vê fretes que ele criou
     * - Driver só vê fretes atribuídos a ele
     */
    public function view(User $user, Freight $freight): bool
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
     * Criar frete — apenas admin e manager.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Atualizar frete:
     * - Admin pode atualizar qualquer frete
     * - Manager só atualiza os que ele criou
     */
    public function update(User $user, Freight $freight): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isManager() && $freight->created_by === $user->id;
    }

    /**
     * Deletar frete — apenas admin.
     */
    public function delete(User $user, Freight $freight): bool
    {
        return $user->isAdmin();
    }

    /**
     * Motorista responde (aceitar/recusar), envia doping ou checklist.
     */
    public function respond(User $user, Freight $freight): bool
    {
        return $user->isDriver() && $freight->driver_id === $user->id;
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
