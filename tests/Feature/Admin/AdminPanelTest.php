<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemLogLevel;
use App\Models\ActivityLog;
use App\Models\RequestLog;
use App\Models\SystemLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SystemLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant  = Tenant::factory()->create();
        $this->admin   = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_admin_can_view_telemetry_summary(): void
    {
        Sanctum::actingAs($this->admin);

        RequestLog::withoutGlobalScopes()->create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->admin->id,
            'request_id'   => fake()->uuid(),
            'method'       => 'GET',
            'uri'          => 'api/v1/me',
            'status_code'  => 200,
            'duration_ms'  => 42,
        ]);

        SystemLog::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->admin->id,
            'level'     => SystemLogLevel::Error,
            'channel'   => 'test',
            'message'   => 'Erro simulado',
        ]);

        $response = $this->getJson('/api/v1/admin/telemetry/summary');

        $response->assertOk()
            ->assertJsonPath('data.requests.total', 1)
            ->assertJsonPath('data.system_errors.total', 1);
    }

    public function test_admin_can_list_system_request_and_activity_logs(): void
    {
        Sanctum::actingAs($this->admin);

        SystemLog::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'level'     => SystemLogLevel::Warning,
            'channel'   => 'google_maps',
            'message'   => 'API timeout',
        ]);

        RequestLog::withoutGlobalScopes()->create([
            'tenant_id'    => $this->tenant->id,
            'user_id'      => $this->admin->id,
            'request_id'   => fake()->uuid(),
            'method'       => 'POST',
            'uri'          => 'api/v1/freights',
            'status_code'  => 201,
            'duration_ms'  => 120,
        ]);

        ActivityLog::create([
            'tenant_id'      => $this->tenant->id,
            'user_id'        => $this->admin->id,
            'action'         => 'freight_created',
            'description'    => 'Frete demo criado',
            'auditable_type' => 'App\\Models\\Freight',
            'auditable_id'   => 1,
        ]);

        $this->getJson('/api/v1/admin/system-logs')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/request-logs')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/activity-logs')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_can_resolve_system_log(): void
    {
        Sanctum::actingAs($this->admin);

        $log = SystemLog::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'level'     => SystemLogLevel::Error,
            'channel'   => 'exception',
            'message'   => 'Falha interna',
        ]);

        $response = $this->postJson("/api/v1/admin/system-logs/{$log->id}/resolve");

        $response->assertOk()
            ->assertJsonPath('data.resolved_at', fn ($value) => $value !== null);
    }

    public function test_manager_cannot_access_admin_panel(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/admin/telemetry/summary')->assertForbidden();
        $this->getJson('/api/v1/admin/system-logs')->assertForbidden();
    }

    public function test_api_request_is_recorded_in_request_logs(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/me')->assertOk();

        $this->assertDatabaseHas('request_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->admin->id,
            'method'    => 'GET',
        ]);
    }

    public function test_system_logger_persists_structured_error(): void
    {
        Sanctum::actingAs($this->admin);

        app(SystemLogger::class)->error('Falha de teste', null, ['freight_id' => 99], 'test');

        $this->assertDatabaseHas('system_logs', [
            'tenant_id' => $this->tenant->id,
            'level'     => 'error',
            'channel'   => 'test',
            'message'   => 'Falha de teste',
        ]);
    }

    public function test_admin_cannot_view_system_logs_from_other_tenant(): void
    {
        Sanctum::actingAs($this->admin);

        $otherTenant = Tenant::factory()->create();

        $log = SystemLog::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'level'     => SystemLogLevel::Error,
            'channel'   => 'exception',
            'message'   => 'Outro tenant',
        ]);

        $this->getJson("/api/v1/admin/system-logs/{$log->id}")->assertNotFound();
    }
}
