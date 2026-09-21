<?php

namespace App\Providers;

use App\Services\InsTraService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer([
            'install.layout',
            'install.welcome',
            'install.environment',
            'install.purchase',
            'install.database',
            'install.complete',
            'install.uninstall',
            'install.partials.steps',
        ], function ($view) {
            $lpIsBd = (defined('LP') && strtoupper((string) LP) === 'BD');
            if (!$lpIsBd) {
                foreach ([base_path('.env'), base_path('.env.example')] as $path) {
                    if (is_file($path)) {
                        $content = @file_get_contents($path);
                        if ($content !== false
                            && preg_match('/^\s*LP\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/mi', $content, $m)
                            && strtoupper(trim((string) $m[1])) === 'BD') {
                            $lpIsBd = true;
                            break;
                        }
                    }
                }
            }
            $view->with('lpIsBd', $lpIsBd);
        });
        // [BYPASS] Installation tracker disabled — no remote tracking.
        // if (!$this->app->runningInConsole()) {
        //     $this->app->make(InsTraService::class)->run(request());
        // }

        // RazorpaySslHelper removed — SSL must be configured properly via php.ini/CAs
        
        // Gate::before(function ($user, $ability) {
        //     if ($user->hasRole('Super-Admin')) {
        //         return true;
        //     }
        // });
    }
}
