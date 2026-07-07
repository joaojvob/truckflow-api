<?php

namespace Tests\Feature;

use App\Models\Freight;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportTest extends TestCase
{

    protected Tenant $tenant;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant  = Tenant::factory()->create();
        $this->manager = User::factory()->manager()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_manager_can_export_financial_report_as_pdf(): void
    {
        Sanctum::actingAs($this->manager);

        Freight::factory()->completed()->create([
            'tenant_id'   => $this->tenant->id,
            'created_by'  => $this->manager->id,
            'total_price' => 5000,
            'completed_at'=> now(),
        ]);

        $response = $this->get('/api/v1/reports/financial/export?format=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_can_export_financial_report_as_xlsx(): void
    {
        Sanctum::actingAs($this->manager);

        Freight::factory()->completed()->create([
            'tenant_id'   => $this->tenant->id,
            'created_by'  => $this->manager->id,
            'total_price' => 3000,
            'completed_at'=> now(),
        ]);

        $response = $this->get('/api/v1/reports/financial/export?format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            $response->headers->get('content-type'),
        );
    }

    public function test_driver_cannot_export_financial_report(): void
    {
        $driver = User::factory()->driver()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($driver);

        $this->get('/api/v1/reports/financial/export?format=pdf')->assertForbidden();
    }
}
