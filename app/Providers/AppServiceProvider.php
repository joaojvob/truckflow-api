<?php

namespace App\Providers;

use App\Contracts\FiscalDocumentProvider;
use App\Contracts\RoutingProvider;
use App\Services\GoogleMapsService;
use App\Services\JavaFiscalDocumentProvider;
use App\Services\JavaGeoRoutingProvider;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RoutingProvider::class, function () {
            return match (config('services.geo.driver')) {
                'java'  => $this->app->make(JavaGeoRoutingProvider::class),
                default => $this->app->make(GoogleMapsService::class),
            };
        });

        $this->app->bind(FiscalDocumentProvider::class, function () {
            return match (config('services.fiscal.driver')) {
                'java'  => $this->app->make(JavaFiscalDocumentProvider::class),
                default => $this->app->make(JavaFiscalDocumentProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-tenant', function (Request $request) {
            $tenantKey = $request->user()?->tenant_id ?? $request->ip();

            return Limit::perMinute((int) config('app.api_rate_limit_per_minute', 120))
                ->by("tenant:{$tenantKey}");
        });

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Sanctum')
                );
            });
    }
}
