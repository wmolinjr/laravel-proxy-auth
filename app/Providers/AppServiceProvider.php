<?php

namespace App\Providers;

use App\Services\OAuth\JwtService;
use App\Services\OAuth\OAuthServerService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register OAuth services as singletons
        $this->app->singleton(JwtService::class, function ($app) {
            return new JwtService();
        });

        $this->app->singleton(OAuthServerService::class, function ($app) {
            return new OAuthServerService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configurar logs personalizados para OAuth
        if ($this->app->environment(['local', 'development'])) {
            \Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/oauth.log'),
            ]);
        }
    }
}
