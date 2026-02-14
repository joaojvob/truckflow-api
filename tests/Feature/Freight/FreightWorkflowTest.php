<?php

namespace Tests\Feature\Freight;

use App\Enums\DopingStatus;
use App\Enums\DriverResponse;
use App\Enums\FreightStatus;
use App\Models\DopingTest;
use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreightWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $manager;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant  = Tenant::factory()->create();
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        $this->driver  = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        // Vincular motorista ao gestor
        $this->manager->drivers()->attach($this->driver->id, ['tenant_id' => $this->tenant->id]);
    }

    // ─── Atribuição ───────────────────────────────────────────

    public function test_manager_can_assign_driver_to_freight(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
            'status'     => FreightStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/assign", [
            'driver_id' => $this->driver->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Assigned->value)
            ->assertJsonPath('message', 'Motorista atribuído ao frete com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'              => $freight->id,
            'status'          => FreightStatus::Assigned->value,
            'driver_response' => DriverResponse::Pending->value,
        ]);

        Notification::assertSentTo($this->driver, \App\Notifications\FreightAssigned::class);
    }

    // ─── Aceitar / Recusar ────────────────────────────────────

    public function test_driver_can_accept_freight(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->assigned()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/accept");

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Accepted->value);

        $this->assertDatabaseHas('freights', [
            'id'              => $freight->id,
            'driver_response' => DriverResponse::Accepted->value,
        ]);

        Notification::assertSentTo($this->manager, \App\Notifications\FreightDriverResponded::class);
    }

    public function test_driver_can_reject_freight(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->assigned()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/reject", [
            'reason' => 'Não tenho disponibilidade nesta data.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Rejected->value);

        $this->assertDatabaseHas('freights', [
            'id'               => $freight->id,
            'driver_response'  => DriverResponse::Rejected->value,
            'rejection_reason' => 'Não tenho disponibilidade nesta data.',
        ]);

        Notification::assertSentTo($this->manager, \App\Notifications\FreightDriverResponded::class);
    }

    public function test_driver_cannot_respond_twice(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/accept");

        $response->assertUnprocessable();
    }

    // ─── Doping ───────────────────────────────────────────────

    public function test_driver_can_submit_doping_test(): void
    {
        Notification::fake();
        Storage::fake('private');
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/doping", [
            'file' => UploadedFile::fake()->create('doping_result.pdf', 500, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Exame de doping enviado com sucesso!');

        $this->assertDatabaseHas('doping_tests', [
            'freight_id' => $freight->id,
            'driver_id'  => $this->driver->id,
            'status'     => DopingStatus::Pending->value,
        ]);

        Notification::assertSentTo($this->manager, \App\Notifications\DopingTestSubmitted::class);
    }

    public function test_manager_can_approve_doping_test(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $dopingTest = DopingTest::create([
            'tenant_id'  => $this->tenant->id,
            'freight_id' => $freight->id,
            'driver_id'  => $this->driver->id,
            'file_path'  => 'doping-tests/test.pdf',
            'status'     => DopingStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/doping/{$dopingTest->id}/review", [
            'approved' => true,
            'notes'    => 'Tudo em ordem.',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Doping aprovado!');

        $this->assertDatabaseHas('doping_tests', [
            'id'     => $dopingTest->id,
            'status' => DopingStatus::Approved->value,
        ]);

        $this->assertDatabaseHas('freights', [
            'id'             => $freight->id,
            'doping_approved' => true,
        ]);

        Notification::assertSentTo($this->driver, \App\Notifications\DopingTestReviewed::class);
    }

    // ─── Checklist ────────────────────────────────────────────

    public function test_driver_can_submit_checklist(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/checklist", [
            'items' => [
                'pneus'        => true,
                'oleo'         => true,
                'luzes'        => true,
                'documentacao' => true,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Checklist enviado com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'                  => $freight->id,
            'checklist_completed' => true,
        ]);

        $this->assertDatabaseHas('checklists', [
            'freight_id' => $freight->id,
        ]);

        Notification::assertSentTo($this->manager, \App\Notifications\ChecklistSubmitted::class);
    }

    // ─── Aprovação da viagem ──────────────────────────────────

    public function test_manager_can_approve_trip(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'           => $this->tenant->id,
            'driver_id'           => $this->driver->id,
            'created_by'          => $this->manager->id,
            'doping_approved'     => true,
            'checklist_completed' => true,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Ready->value)
            ->assertJsonPath('message', 'Viagem liberada com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'               => $freight->id,
            'status'           => FreightStatus::Ready->value,
            'manager_approved' => true,
        ]);

        Notification::assertSentTo($this->driver, \App\Notifications\FreightApproved::class);
    }

    public function test_manager_cannot_approve_without_doping(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'           => $this->tenant->id,
            'driver_id'           => $this->driver->id,
            'created_by'          => $this->manager->id,
            'doping_approved'     => false,
            'checklist_completed' => true,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('doping');
    }

    public function test_manager_cannot_approve_without_checklist(): void
    {
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'           => $this->tenant->id,
            'driver_id'           => $this->driver->id,
            'created_by'          => $this->manager->id,
            'doping_approved'     => true,
            'checklist_completed' => false,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('checklist');
    }

    // ─── Início e conclusão da viagem ─────────────────────────

    public function test_driver_can_start_trip_when_ready(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->ready()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::InTransit->value)
            ->assertJsonPath('message', 'Viagem iniciada com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => FreightStatus::InTransit->value,
        ]);

        Notification::assertSentTo($this->manager, \App\Notifications\FreightStatusChanged::class);
    }

    public function test_driver_cannot_start_trip_without_approval(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->accepted()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/start");

        $response->assertUnprocessable();
    }

    public function test_driver_can_complete_trip(): void
    {
        Notification::fake();
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->inTransit()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/complete", [
            'rating' => 5,
            'notes'  => 'Viagem tranquila, sem intercorrências.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', FreightStatus::Completed->value)
            ->assertJsonPath('message', 'Viagem finalizada com sucesso!');

        $this->assertDatabaseHas('freights', [
            'id'     => $freight->id,
            'status' => FreightStatus::Completed->value,
        ]);

        Notification::assertSentTo($this->manager, \App\Notifications\FreightStatusChanged::class);
    }

    public function test_driver_cannot_complete_pending_trip(): void
    {
        Sanctum::actingAs($this->driver);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
            'status'     => FreightStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/complete");

        $response->assertUnprocessable();
    }

    // ─── Autorização ──────────────────────────────────────────

    public function test_admin_cannot_accept_freight(): void
    {
        $admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        Sanctum::actingAs($admin);

        $freight = Freight::factory()->assigned()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/accept");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_workflow(): void
    {
        $freight = Freight::factory()->assigned()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->postJson("/api/v1/freights/{$freight->id}/accept");

        $response->assertUnauthorized();
    }

    // ─── Fluxo completo E2E ───────────────────────────────────

    public function test_complete_workflow_e2e(): void
    {
        Notification::fake();
        Storage::fake('private');

        // 1. Gestor cria o frete
        Sanctum::actingAs($this->manager);

        $freight = Freight::factory()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
            'status'     => FreightStatus::Pending,
        ]);

        // 2. Gestor atribui motorista
        $this->postJson("/api/v1/freights/{$freight->id}/assign", [
            'driver_id' => $this->driver->id,
        ])->assertOk();

        // 3. Motorista aceita
        Sanctum::actingAs($this->driver);
        $this->postJson("/api/v1/freights/{$freight->id}/accept")->assertOk();

        // 4. Motorista envia doping
        $this->postJson("/api/v1/freights/{$freight->id}/doping", [
            'file' => UploadedFile::fake()->create('doping.pdf', 200, 'application/pdf'),
        ])->assertCreated();

        // 5. Motorista envia checklist
        $this->postJson("/api/v1/freights/{$freight->id}/checklist", [
            'items' => ['pneus' => true, 'oleo' => true, 'luzes' => true],
        ])->assertOk();

        // 6. Gestor aprova doping
        Sanctum::actingAs($this->manager);
        $dopingTest = DopingTest::where('freight_id', $freight->id)->first();
        $this->postJson("/api/v1/freights/{$freight->id}/doping/{$dopingTest->id}/review", [
            'approved' => true,
        ])->assertOk();

        // 7. Gestor libera viagem
        $this->postJson("/api/v1/freights/{$freight->id}/approve")->assertOk();

        // 8. Motorista inicia viagem
        Sanctum::actingAs($this->driver);
        $this->postJson("/api/v1/freights/{$freight->id}/start")->assertOk();

        // 9. Motorista finaliza viagem
        $this->postJson("/api/v1/freights/{$freight->id}/complete", [
            'rating' => 5,
            'notes'  => 'Tudo perfeito!',
        ])->assertOk();

        // Verifica estado final
        $freight->refresh();
        $this->assertEquals(FreightStatus::Completed, $freight->status);
        $this->assertTrue($freight->doping_approved);
        $this->assertTrue($freight->manager_approved);
        $this->assertTrue($freight->checklist_completed);
        $this->assertNotNull($freight->started_at);
        $this->assertNotNull($freight->completed_at);
    }
}
