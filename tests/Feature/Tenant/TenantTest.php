<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_tenant_can_create_company(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tenant', [
            'name' => 'Minha Transportadora',
            'slug' => 'minha-transportadora',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Minha Transportadora')
            ->assertJsonPath('message', 'Empresa criada com sucesso!');

        $user->refresh();
        $this->assertNotNull($user->tenant_id);
        $this->assertEquals('admin', $user->role->value);
    }

    public function test_user_with_tenant_cannot_create_another_company(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tenant', [
            'name' => 'Outra Empresa',
            'slug' => 'outra-empresa',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Você já está vinculado a uma empresa.');
    }

    public function test_admin_can_view_own_company(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Alpha Transportes']);
        $admin  = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/tenant');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Alpha Transportes');
    }

    public function test_admin_can_update_company(): void
    {
        $tenant = Tenant::factory()->create();
        $admin  = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/tenant', [
            'name' => 'Nome Atualizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nome Atualizado')
            ->assertJsonPath('message', 'Empresa atualizada com sucesso!');
    }

    public function test_driver_cannot_update_company(): void
    {
        $tenant = Tenant::factory()->create();
        $driver = User::factory()->driver()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($driver);

        $response = $this->putJson('/api/v1/tenant', [
            'name' => 'Tentativa',
        ]);

        $response->assertForbidden();
    }
}
