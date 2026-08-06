<?php

namespace Modules\BusinessClub\Providers;

use Illuminate\Support\ServiceProvider;

class BusinessClubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'businessclub');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
