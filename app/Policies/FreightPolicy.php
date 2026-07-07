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

    /**
     * Calcular rota — admin e gestor responsável pelo frete.
     */
    public function calculateRoute(User $user, Freight $freight): bool
    {
        return $this->update($user, $freight);
    }

    /**
     * Enviar posição GPS — motorista do frete em trânsito.
     */
    public function track(User $user, Freight $freight): bool
    {
        return $user->isDriver() && $freight->driver_id === $user->id;
    }

    /**
     * Visualizar relatórios — admin e gestor.
     */
    public function viewReports(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Emitir/cancelar CT-e — admin ou gestor responsável pelo frete.
     */
    public function emitFiscal(User $user, Freight $freight): bool
    {
        return $this->update($user, $freight);
    }

    /**
     * Visualizar documentos fiscais do frete.
     */
    public function viewFiscal(User $user, Freight $freight): bool
    {
        return $this->view($user, $freight);
    }
}
