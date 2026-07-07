<?php

namespace Tests\Feature;

use App\Models\DriverProfile;
use App\Models\Tenant;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    protected Tenant $tenant;

    protected User $driver;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->tenant = Tenant::factory()->create();
        $this->driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_driver_can_upload_cnh(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->postJson('/api/v1/driver-profile/cnh', [
            'file' => UploadedFile::fake()->create('cnh.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.cnh_has_document', true);

        $profile = DriverProfile::where('user_id', $this->driver->id)->first();
        Storage::disk('private')->assertExists($profile->cnh_file_path);
    }

    public function test_manager_can_download_driver_cnh(): void
    {
        Sanctum::actingAs($this->driver);

        $this->postJson('/api/v1/driver-profile/cnh', [
            'file' => UploadedFile::fake()->create('cnh.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        Sanctum::actingAs($this->manager);

        $this->get("/api/v1/users/{$this->driver->id}/cnh")
            ->assertOk();
    }

    public function test_driver_can_download_own_cnh(): void
    {
        Sanctum::actingAs($this->driver);

        $this->postJson('/api/v1/driver-profile/cnh', [
            'file' => UploadedFile::fake()->create('cnh.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $this->get('/api/v1/driver-profile/cnh')
            ->assertOk();
    }

    public function test_driver_can_upload_truck_crlv(): void
    {
        Sanctum::actingAs($this->driver);

        $truck = Truck::factory()->create([
            'tenant_id' => $this->tenant->id,
            'driver_id' => $this->driver->id,
        ]);

        $response = $this->postJson("/api/v1/trucks/{$truck->id}/crlv", [
            'file'        => UploadedFile::fake()->create('crlv.pdf', 100, 'application/pdf'),
            'crlv_expiry' => now()->addYear()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.crlv_has_document', true);

        Storage::disk('private')->assertExists($truck->fresh()->crlv_file_path);
    }

    public function test_admin_cannot_upload_cnh(): void
    {
        $admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/driver-profile/cnh', [
            'file' => UploadedFile::fake()->create('cnh.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }
}
