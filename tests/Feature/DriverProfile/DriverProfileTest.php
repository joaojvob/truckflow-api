<?php

namespace Tests\Feature\DriverProfile;

use App\Models\DriverProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverProfileTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_driver_can_view_own_profile(): void
    {
        Sanctum::actingAs($this->driver);

        DriverProfile::factory()->create([
            'user_id'   => $this->driver->id,
            'tenant_id' => $this->tenant->id,
            'cpf'       => '123.456.789-00',
        ]);

        $response = $this->getJson('/api/v1/driver-profile');

        $response->assertOk()
            ->assertJsonPath('data.cpf', '123.456.789-00');
    }

    public function test_driver_without_profile_gets_null(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->getJson('/api/v1/driver-profile');

        $response->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Perfil de motorista ainda não cadastrado.');
    }

    public function test_driver_can_create_profile(): void
    {
        Sanctum::actingAs($this->driver);

        $payload = [
            'phone'                  => '(11) 99999-8888',
            'cpf'                    => '111.222.333-44',
            'birth_date'             => '1990-05-15',
            'cnh_number'             => '12345678900',
            'cnh_category'           => 'E',
            'cnh_expiry'             => '2027-12-31',
            'address'                => 'Rua Teste, 123',
            'city'                   => 'São Paulo',
            'state'                  => 'SP',
            'zip_code'               => '01001-000',
            'emergency_contact_name' => 'Maria',
            'emergency_contact_phone' => '(11) 98888-7777',
        ];

        $response = $this->putJson('/api/v1/driver-profile', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.cpf', '111.222.333-44')
            ->assertJsonPath('data.cnh_category', 'E')
            ->assertJsonPath('message', 'Perfil de motorista criado com sucesso!');

        $this->assertDatabaseHas('driver_profiles', [
            'user_id'    => $this->driver->id,
            'cnh_number' => '12345678900',
        ]);
    }

    public function test_driver_can_update_existing_profile(): void
    {
        Sanctum::actingAs($this->driver);

        DriverProfile::factory()->create([
            'user_id'   => $this->driver->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->putJson('/api/v1/driver-profile', [
            'phone'   => '(21) 91111-2222',
            'address' => 'Rua Nova, 456',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Perfil de motorista atualizado com sucesso!');
    }
}
