<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PaymentGatewayService;

/**
 * Payment Gateway Service Provider
 * 
 * Registers payment gateway services and facades
 */
class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register PaymentGatewayService as singleton
        $this->app->singleton(PaymentGatewayService::class, function ($app) {
            return new PaymentGatewayService();
        });

        // Alias for easier access
        $this->app->alias(PaymentGatewayService::class, 'payment.gateway');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
