<?php

namespace App\Services;

use App\Enums\DopingStatus;
use App\Enums\DriverResponse;
use App\Enums\FreightStatus;
use App\Models\DopingTest;
use App\Models\Freight;
use App\Models\User;
use App\Notifications\ChecklistSubmitted;
use App\Notifications\DopingTestReviewed;
use App\Notifications\DopingTestSubmitted;
use App\Notifications\FreightApproved;
use App\Notifications\FreightAssigned;
use App\Notifications\FreightDriverResponded;
use App\Notifications\FreightStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orquestra o workflow completo gestor ↔ motorista (atribuição até conclusão da viagem).
 *
 * Cada transição de estado dispara notificações e registros de auditoria.
 */
class FreightWorkflowService
{
    // ─── 1. Gestor atribui frete ao motorista ─────────────────

    /**
     * Gestor atribui um frete a um motorista — motorista é notificado.
     *
     * @param  Freight  $freight  Frete pendente ou recusado anteriormente.
     * @param  User  $driver  Motorista do mesmo tenant.
     * @return Freight Frete com status `assigned`.
     *
     * @throws ValidationException Se o frete não aceitar atribuição.
     */
    public function assignDriver(Freight $freight, User $driver): Freight
    {
        if ($freight->status !== FreightStatus::Pending && $freight->status !== FreightStatus::Rejected) {
            throw ValidationException::withMessages([
                'status' => "Frete {$freight->status->label()} não pode receber atribuição de motorista.",
            ]);
        }

        return DB::transaction(function () use ($freight, $driver) {
            $freight->update([
                'driver_id'        => $driver->id,
                'status'           => FreightStatus::Assigned,
                'driver_response'  => DriverResponse::Pending,
                'rejection_reason' => null,
                'driver_responded_at' => null,
            ]);

            $freight->recordActivity(
                action: 'freight_assigned',
                description: "Frete atribuído ao motorista {$driver->name}",
                payload: ['driver_id' => $driver->id],
            );

            // Notificar motorista
            $driver->notify(new FreightAssigned($freight));

            return $freight->fresh(['driver', 'creator']);
        });
    }

    // ─── 2. Motorista aceita ou recusa ────────────────────────

    /**
     * Motorista aceita o frete atribuído.
     *
     * @param  Freight  $freight  Frete em status `assigned`.
     * @return Freight Frete com status `accepted`.
     *
     * @throws ValidationException Se o motorista já respondeu ou status inválido.
     */
    public function acceptFreight(Freight $freight): Freight
    {
        $this->validateDriverCanRespond($freight);

        return DB::transaction(function () use ($freight) {
            $freight->update([
                'status'              => FreightStatus::Accepted,
                'driver_response'     => DriverResponse::Accepted,
                'driver_responded_at' => now(),
            ]);

            $freight->recordActivity(
                action: 'freight_accepted',
                description: "Motorista {$freight->driver->name} aceitou o frete",
            );

            // Notificar gestor que criou o frete
            $this->notifyManager($freight, true);

            return $freight->fresh();
        });
    }

    /**
     * Motorista recusa o frete atribuído.
     *
     * @param  Freight  $freight  Frete em status `assigned`.
     * @param  string|null  $reason  Motivo opcional da recusa.
     * @return Freight Frete com status `rejected`.
     *
     * @throws ValidationException Se o motorista já respondeu ou status inválido.
     */
    public function rejectFreight(Freight $freight, ?string $reason = null): Freight
    {
        $this->validateDriverCanRespond($freight);

        return DB::transaction(function () use ($freight, $reason) {
            $freight->update([
                'status'              => FreightStatus::Rejected,
                'driver_response'     => DriverResponse::Rejected,
                'rejection_reason'    => $reason,
                'driver_responded_at' => now(),
            ]);

            $freight->recordActivity(
                action: 'freight_rejected',
                description: "Motorista {$freight->driver->name} recusou o frete",
                payload: ['reason' => $reason],
            );

            // Notificar gestor
            $this->notifyManager($freight, false, $reason);

            return $freight->fresh();
        });
    }

    // ─── 3. Motorista envia exame de doping ───────────────────

    /**
     * Motorista envia o exame de doping para um frete aceito.
     *
     * @param  Freight  $freight  Frete em status `accepted`.
     * @param  string  $filePath  Caminho do arquivo no storage privado.
     * @return DopingTest Registro do exame pendente de análise.
     *
     * @throws ValidationException Se o frete não estiver aceito.
     */
    public function submitDopingTest(Freight $freight, string $filePath): DopingTest
    {
        if ($freight->status !== FreightStatus::Accepted) {
            throw ValidationException::withMessages([
                'status' => 'Exame de doping só pode ser enviado após aceitar o frete.',
            ]);
        }

        return DB::transaction(function () use ($freight, $filePath) {
            $dopingTest = DopingTest::create([
                'tenant_id'  => $freight->tenant_id,
                'freight_id' => $freight->id,
                'driver_id'  => $freight->driver_id,
                'file_path'  => $filePath,
                'status'     => DopingStatus::Pending,
            ]);

            $freight->recordActivity(
                action: 'doping_submitted',
                description: "Motorista enviou exame de doping",
            );

            // Notificar gestor
            if ($freight->creator) {
                $freight->creator->notify(new DopingTestSubmitted($dopingTest));
            }

            return $dopingTest->fresh();
        });
    }

    // ─── 4. Gestor aprova/rejeita doping ──────────────────────

    /**
     * Gestor aprova ou reprova o exame de doping.
     *
     * @param  DopingTest  $dopingTest  Exame pendente.
     * @param  bool  $approved  true = aprovado, false = reprovado.
     * @param  string|null  $notes  Observações do gestor.
     * @return DopingTest Exame atualizado.
     *
     * @throws ValidationException Se o exame já foi analisado.
     */
    public function reviewDopingTest(DopingTest $dopingTest, bool $approved, ?string $notes = null): DopingTest
    {
        if (! $dopingTest->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Este exame já foi analisado.',
            ]);
        }

        return DB::transaction(function () use ($dopingTest, $approved, $notes) {
            $dopingTest->update([
                'status'         => $approved ? DopingStatus::Approved : DopingStatus::Rejected,
                'reviewer_notes' => $notes,
                'reviewed_by'    => auth()->id(),
                'reviewed_at'    => now(),
            ]);

            $freight = $dopingTest->freight;

            if ($approved) {
                $freight->update(['doping_approved' => true]);
            }

            $freight->recordActivity(
                action: $approved ? 'doping_approved' : 'doping_rejected',
                description: $approved ? 'Exame de doping aprovado' : 'Exame de doping reprovado',
                payload: ['notes' => $notes],
            );

            // Notificar motorista
            $dopingTest->driver->notify(new DopingTestReviewed($dopingTest));

            return $dopingTest->fresh();
        });
    }

    // ─── 5. Motorista envia checklist (gestor aprova) ─────────

    /**
     * Motorista envia checklist pré-viagem — gestor é notificado.
     *
     * @param  Freight  $freight  Frete em status `accepted`.
     * @param  array<string, bool>  $checklistData  Itens do checklist (pneus, óleo, etc.).
     * @return Freight Frete com `checklist_completed` = true.
     *
     * @throws ValidationException Se o frete não estiver aceito.
     */
    public function submitChecklist(Freight $freight, array $checklistData): Freight
    {
        if ($freight->status !== FreightStatus::Accepted) {
            throw ValidationException::withMessages([
                'status' => 'Checklist só pode ser enviado após aceitar o frete.',
            ]);
        }

        return DB::transaction(function () use ($freight, $checklistData) {
            $freight->checklists()->create([
                'tenant_id' => $freight->tenant_id,
                'items'     => $checklistData,
            ]);

            $freight->update(['checklist_completed' => true]);

            $freight->recordActivity(
                action: 'checklist_submitted',
                description: 'Motorista enviou o checklist pré-viagem',
                payload: ['items' => $checklistData],
            );

            // Notificar gestor
            if ($freight->creator) {
                $freight->creator->notify(new ChecklistSubmitted($freight, $checklistData));
            }

            return $freight->fresh();
        });
    }

    // ─── 6. Gestor libera a viagem ────────────────────────────

    /**
     * Gestor libera a viagem após doping e checklist aprovados.
     *
     * @param  Freight  $freight  Frete em status `accepted` com pré-requisitos ok.
     * @return Freight Frete com status `ready`.
     *
     * @throws ValidationException Se doping ou checklist estiverem pendentes.
     */
    public function approveTrip(Freight $freight): Freight
    {
        if ($freight->status !== FreightStatus::Accepted) {
            throw ValidationException::withMessages([
                'status' => "Frete {$freight->status->label()} não pode ser liberado.",
            ]);
        }

        if (! $freight->doping_approved) {
            throw ValidationException::withMessages([
                'doping' => 'O exame de doping precisa ser aprovado antes de liberar a viagem.',
            ]);
        }

        if (! $freight->checklist_completed) {
            throw ValidationException::withMessages([
                'checklist' => 'O checklist precisa ser enviado antes de liberar a viagem.',
            ]);
        }

        return DB::transaction(function () use ($freight) {
            $freight->update([
                'status'           => FreightStatus::Ready,
                'manager_approved' => true,
                'approved_by'      => auth()->id(),
                'approved_at'      => now(),
            ]);

            $freight->recordActivity(
                action: 'trip_approved',
                description: 'Gestor liberou a viagem',
            );

            // Notificar motorista
            $freight->driver->notify(new FreightApproved($freight));

            return $freight->fresh();
        });
    }

    // ─── 7. Motorista inicia a viagem ─────────────────────────

    /**
     * Motorista inicia a viagem após liberação do gestor.
     *
     * @param  Freight  $freight  Frete em status `ready`.
     * @return Freight Frete com status `in_transit`.
     *
     * @throws ValidationException Se pré-requisitos não estiverem completos.
     */
    public function startTrip(Freight $freight): Freight
    {
        if ($freight->status !== FreightStatus::Ready) {
            throw ValidationException::withMessages([
                'status' => "Frete não pode ser iniciado. Status atual: {$freight->status->label()}",
            ]);
        }

        if (! $freight->isReadyToStart()) {
            throw ValidationException::withMessages([
                'approval' => 'O frete precisa ter doping aprovado, checklist enviado e aprovação do gestor.',
            ]);
        }

        return DB::transaction(function () use ($freight) {
            $freight->update([
                'status'     => FreightStatus::InTransit,
                'started_at' => now(),
            ]);

            $freight->recordActivity(
                action: 'trip_started',
                description: "Motorista iniciou a viagem: {$freight->cargo_name}",
            );

            // Notificar gestor
            if ($freight->creator) {
                $freight->creator->notify(new FreightStatusChanged(
                    $freight,
                    'trip_started',
                    "Motorista {$freight->driver->name} iniciou a viagem do frete \"{$freight->cargo_name}\"",
                ));
            }

            return $freight->fresh();
        });
    }

    // ─── 8. Motorista finaliza a viagem ───────────────────────

    /**
     * Motorista finaliza a viagem em trânsito.
     *
     * @param  Freight  $freight  Frete em status `in_transit`.
     * @param  int|null  $rating  Avaliação opcional do frete (1-5).
     * @param  string|null  $notes  Observações finais do motorista.
     * @return Freight Frete com status `completed`.
     *
     * @throws ValidationException Se o frete não estiver em trânsito.
     */
    public function completeTrip(Freight $freight, ?int $rating = null, ?string $notes = null): Freight
    {
        if ($freight->status !== FreightStatus::InTransit) {
            throw ValidationException::withMessages([
                'status' => "Frete não pode ser finalizado. Status atual: {$freight->status->label()}",
            ]);
        }

        return DB::transaction(function () use ($freight, $rating, $notes) {
            $freight->update([
                'status'        => FreightStatus::Completed,
                'completed_at'  => now(),
                'driver_rating' => $rating,
                'driver_notes'  => $notes,
            ]);

            $freight->recordActivity(
                action: 'trip_completed',
                description: "Motorista finalizou a viagem: {$freight->cargo_name}",
                payload: ['rating' => $rating, 'notes' => $notes],
            );

            // Notificar gestor
            if ($freight->creator) {
                $freight->creator->notify(new FreightStatusChanged(
                    $freight,
                    'trip_completed',
                    "Motorista {$freight->driver->name} finalizou o frete \"{$freight->cargo_name}\"",
                ));
            }

            return $freight->fresh();
        });
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Valida se o motorista autenticado pode aceitar/recusar o frete.
     *
     * @param  Freight  $freight  Frete a ser respondido.
     *
     * @throws ValidationException Status ou resposta inválidos.
     */
    private function validateDriverCanRespond(Freight $freight): void
    {
        if ($freight->status !== FreightStatus::Assigned) {
            throw ValidationException::withMessages([
                'status' => "Frete {$freight->status->label()} não pode receber resposta do motorista.",
            ]);
        }

        if ($freight->driver_response !== DriverResponse::Pending) {
            throw ValidationException::withMessages([
                'driver_response' => 'Motorista já respondeu a este frete.',
            ]);
        }
    }

    /**
     * Notifica o gestor criador do frete sobre aceite ou recusa.
     *
     * @param  Freight  $freight  Frete respondido.
     * @param  bool  $accepted  true = aceito, false = recusado.
     * @param  string|null  $reason  Motivo da recusa.
     */
    private function notifyManager(Freight $freight, bool $accepted, ?string $reason = null): void
    {
        $creator = $freight->creator;

        if ($creator) {
            $creator->notify(new FreightDriverResponded($freight, $accepted, $reason));
        }
    }
}
