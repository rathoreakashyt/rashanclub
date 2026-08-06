<?php

namespace Modules\Administrator\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AdministratorServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Administrator';

    protected string $nameLower = 'administrator';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        
        // Register AttendanceRepository
        $this->app->singleton(\Modules\Administrator\Repositories\AttendanceRepository::class, function ($app) {
            return new \Modules\Administrator\Repositories\AttendanceRepository(new \Modules\Administrator\Models\Attendance());
        });
        
        // Register AttendanceService as singleton
        $this->app->singleton(\Modules\Administrator\Services\AttendanceService::class, function ($app) {
            return new \Modules\Administrator\Services\AttendanceService($app->make(\Modules\Administrator\Repositories\AttendanceRepository::class));
        });
        
        // Register UserRepository
        $this->app->singleton(\Modules\Administrator\Repositories\UserRepository::class, function ($app) {
            return new \Modules\Administrator\Repositories\UserRepository(new \App\Models\User());
        });
        
        // Register UserService as singleton
        $this->app->singleton(\Modules\Administrator\Services\UserService::class, function ($app) {
            return new \Modules\Administrator\Services\UserService($app->make(\Modules\Administrator\Repositories\UserRepository::class));
        });
        
        // Register RoleRepository
        $this->app->singleton(\Modules\Administrator\Repositories\RoleRepository::class, function ($app) {
            return new \Modules\Administrator\Repositories\RoleRepository(
                new \Modules\Administrator\Models\Role(),
                new \Spatie\Permission\Models\Permission()
            );
        });
        
        // Register RoleService as singleton
        $this->app->singleton(\Modules\Administrator\Services\RoleService::class, function ($app) {
            return new \Modules\Administrator\Services\RoleService($app->make(\Modules\Administrator\Repositories\RoleRepository::class));
        });
        
        // Register SalaryRepository
        $this->app->singleton(\Modules\Administrator\Repositories\SalaryRepository::class, function ($app) {
            return new \Modules\Administrator\Repositories\SalaryRepository(new \Modules\Administrator\Models\Salary());
        });
        
        // Register SalaryService as singleton
        $this->app->singleton(\Modules\Administrator\Services\SalaryService::class, function ($app) {
            return new \Modules\Administrator\Services\SalaryService($app->make(\Modules\Administrator\Repositories\SalaryRepository::class));
        });

        // Register EmployeeAdvancePaymentRepository
        $this->app->singleton(\Modules\Administrator\Repositories\EmployeeAdvancePaymentRepository::class, function ($app) {
            return new \Modules\Administrator\Repositories\EmployeeAdvancePaymentRepository(new \Modules\Administrator\Models\EmployeeAdvancePayment());
        });

        // Register EmployeeAdvancePaymentService as singleton
        $this->app->singleton(\Modules\Administrator\Services\EmployeeAdvancePaymentService::class, function ($app) {
            return new \Modules\Administrator\Services\EmployeeAdvancePaymentService($app->make(\Modules\Administrator\Repositories\EmployeeAdvancePaymentRepository::class));
        });
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $config = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
                    $segments = explode('.', $this->nameLower.'.'.$config_key);

                    // Remove duplicated adjacent segments
                    $normalized = [];
                    foreach ($segments as $segment) {
                        if (end($normalized) !== $segment) {
                            $normalized[] = $segment;
                        }
                    }

                    $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

                    $this->publishes([$file->getPathname() => config_path($config)], 'config');
                    $this->merge_config_from($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Merge config from the given path recursively.
     */
    protected function merge_config_from(string $path, string $key): void
    {
        $existing = config($key, []);
        $module_config = require $path;

        config([$key => array_replace_recursive($existing, $module_config)]);
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\' . $this->name . '\\View\\Components', $this->nameLower);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
