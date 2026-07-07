<?php

namespace Tests\Feature\Fiscal;

use App\Contracts\FiscalDocumentProvider;
use App\Enums\FiscalDocumentStatus;
use App\Models\Freight;
use App\Models\FreightFiscalDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class FiscalDocumentTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $manager;

    protected User $driver;

    protected Freight $freight;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->tenant = Tenant::factory()->create([
            'settings' => [
                'fiscal' => [
                    'cnpj'         => '12345678000199',
                    'ie'           => '123456789',
                    'razao_social' => 'Transportadora Teste LTDA',
                    'uf'           => 'SP',
                    'municipio'    => 'São Paulo',
                ],
            ],
        ]);

        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
        $this->driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        $this->manager->drivers()->attach($this->driver->id, ['tenant_id' => $this->tenant->id]);

        $this->freight = Freight::factory()->completed()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_admin_can_configure_tenant_fiscal_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/tenant/fiscal', [
            'cnpj'         => '98765432000111',
            'ie'           => '987654321',
            'razao_social' => 'Nova Transportadora SA',
            'uf'           => 'RJ',
            'municipio'    => 'Rio de Janeiro',
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.fiscal.cnpj', '98765432000111')
            ->assertJsonPath('data.settings.fiscal.uf', 'RJ');
    }

    public function test_manager_can_emit_cte_for_completed_freight(): void
    {
        $this->mockFiscalEmit('35260712345678000199570010000000011234567890');

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/cte");

        $response->assertCreated()
            ->assertJsonPath('data.type', 'cte')
            ->assertJsonPath('data.status', FiscalDocumentStatus::Authorized->value)
            ->assertJsonPath('data.access_key', '35260712345678000199570010000000011234567890')
            ->assertJsonPath('data.has_xml', true)
            ->assertJsonPath('data.has_pdf', true);

        $this->assertDatabaseHas('freight_fiscal_documents', [
            'freight_id' => $this->freight->id,
            'access_key' => '35260712345678000199570010000000011234567890',
            'status'     => FiscalDocumentStatus::Authorized->value,
        ]);

        $document = FreightFiscalDocument::first();
        Storage::disk('private')->assertExists($document->xml_path);
        Storage::disk('private')->assertExists($document->pdf_path);
    }

    public function test_cannot_emit_cte_for_non_completed_freight(): void
    {
        $freight = Freight::factory()->inTransit()->create([
            'tenant_id'  => $this->tenant->id,
            'driver_id'  => $this->driver->id,
            'created_by' => $this->manager->id,
        ]);

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/freights/{$freight->id}/fiscal-documents/cte")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_emit_duplicate_cte(): void
    {
        $this->mockFiscalEmit('35260712345678000199570010000000011234567891');

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/cte")->assertCreated();

        $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/cte")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fiscal']);
    }

    public function test_cannot_emit_cte_without_fiscal_settings(): void
    {
        $tenant = Tenant::factory()->create(['settings' => []]);
        $manager = User::factory()->manager()->create(['tenant_id' => $tenant->id]);
        $driver = User::factory()->driver()->create(['tenant_id' => $tenant->id]);

        $freight = Freight::factory()->completed()->create([
            'tenant_id'  => $tenant->id,
            'driver_id'  => $driver->id,
            'created_by' => $manager->id,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/freights/{$freight->id}/fiscal-documents/cte")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fiscal']);
    }

    public function test_manager_can_list_and_download_fiscal_documents(): void
    {
        $this->mockFiscalEmit();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/cte")->assertCreated();

        $document = FreightFiscalDocument::first();

        $this->getJson("/api/v1/freights/{$this->freight->id}/fiscal-documents")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $document->id);

        $this->get("/api/v1/freights/{$this->freight->id}/fiscal-documents/{$document->id}/xml")
            ->assertOk();

        $this->get("/api/v1/freights/{$this->freight->id}/fiscal-documents/{$document->id}/pdf")
            ->assertOk();
    }

    public function test_manager_can_cancel_authorized_cte(): void
    {
        $accessKey = '35260712345678000199570010000000011234567890';

        $this->mock(FiscalDocumentProvider::class, function (MockInterface $mock) use ($accessKey) {
            $mock->shouldReceive('emitCte')->once()->andReturn($this->emitPayload($accessKey));
            $mock->shouldReceive('cancelCte')->once()->with($accessKey, 'Erro na emissão do documento fiscal')->andReturn([
                'access_key'      => $accessKey,
                'protocol_number' => '135260000000099',
                'status'          => 'cancelled',
                'message'         => 'CT-e cancelado (mock)',
            ]);
        });

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/cte")->assertCreated();

        $document = FreightFiscalDocument::first();

        $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/{$document->id}/cancel", [
            'reason' => 'Erro na emissão do documento fiscal',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', FiscalDocumentStatus::Cancelled->value);
    }

    public function test_driver_cannot_emit_cte(): void
    {
        Sanctum::actingAs($this->driver);

        $this->postJson("/api/v1/freights/{$this->freight->id}/fiscal-documents/cte")
            ->assertForbidden();
    }

    private function mockFiscalEmit(string $accessKey = '35260712345678000199570010000000011234567890'): void
    {
        $this->mock(FiscalDocumentProvider::class, function (MockInterface $mock) use ($accessKey) {
            $mock->shouldReceive('emitCte')->andReturn($this->emitPayload($accessKey));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function emitPayload(string $accessKey): array
    {
        return [
            'access_key'      => $accessKey,
            'protocol_number' => '135260000000001',
            'status'          => 'authorized',
            'xml_content'     => '<?xml version="1.0"?><cteProc><CTe/></cteProc>',
            'pdf_base64'      => base64_encode('%PDF-1.4 mock dacte'),
            'message'         => 'CT-e autorizado (mock)',
        ];
    }
}
