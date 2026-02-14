<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'driver@test.com',
            'password'  => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'driver@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'Success')
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email'     => 'driver@test.com',
            'password'  => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'driver@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'Novo Motorista',
            'email'                 => 'novo@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'tenant_id'             => $this->tenant->id,
            'role'                  => 'driver',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'Success')
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'novo@test.com',
            'role'  => 'driver',
        ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/logout');

        $response->assertStatus(200);

        // Token foi revogado
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.tenant.id', $this->tenant->id);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }
}
