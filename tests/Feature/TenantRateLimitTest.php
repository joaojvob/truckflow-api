<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantRateLimitTest extends TestCase
{
    public function test_api_requests_are_rate_limited_per_tenant(): void
    {
        config(['app.api_rate_limit_per_minute' => 3]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($user);

        RateLimiter::clear('api-tenant');

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/me')->assertOk();
        }

        $this->getJson('/api/v1/me')->assertStatus(429);
    }

    public function test_different_tenants_have_independent_rate_limits(): void
    {
        config(['app.api_rate_limit_per_minute' => 2]);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->admin()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->admin()->create(['tenant_id' => $tenantB->id]);

        RateLimiter::clear('api-tenant');

        Sanctum::actingAs($userA);
        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/me')->assertStatus(429);

        Sanctum::actingAs($userB);
        $this->getJson('/api/v1/me')->assertOk();
    }
}
