<?php

use App\Services\InsTraService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('installation-tracker:status', function () {
    $tracker = app(InsTraService::class);
    $path = base_path('bootstrap/system/.htaccess');
    $exists = is_file($path);
    $this->info('bootstrap/system/.htaccess exists: ' . ($exists ? 'yes' : 'no'));
    if ($exists) {
        $content = file_get_contents($path);
        $this->info('Counter value: ' . (is_numeric(trim($content)) ? trim($content) : '(non-numeric)'));
    }
})->purpose('Show installation tracker counter status');

Artisan::command('installation-tracker:reset {value=1 : Counter value to write (e.g. 9 to test next hit triggers CURL)}', function () {
    $path = base_path('bootstrap/system/.htaccess');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $value = (int) $this->argument('value');
    file_put_contents($path, (string) $value, LOCK_EX);
    $this->info("Counter set to {$value}. Next Sale/POS request will make it " . ($value + 1) . ($value % 10 === 9 ? ' (and trigger CURL).' : '.'));
})->purpose('Reset installation tracker counter for testing');

// Clear Laravel/software cache (config, view, route, application cache, compiled)
Artisan::command('cache:clear-software', function () {
    Artisan::call('config:clear');
    $this->info('Configuration cache cleared.');
    Artisan::call('view:clear');
    $this->info('View cache cleared.');
    Artisan::call('route:clear');
    $this->info('Route cache cleared.');
    Artisan::call('cache:clear');
    $this->info('Application cache cleared.');
    if (class_exists(\Illuminate\Foundation\Console\ClearCompiledCommand::class)) {
        Artisan::call('clear-compiled');
        $this->info('Compiled files cleared.');
    }
    $this->newLine();
    $this->info('Software cache cleared successfully.');
})->purpose('Clear all Laravel/software cache (config, view, route, app cache)');

// Bump PWA service worker cache version so browser clears old PWA cache on next load
Artisan::command('cache:clear-pwa', function () {
    $path = public_path('pwa/serviceworker.js');
    if (!is_file($path)) {
        $this->error('PWA service worker not found: ' . $path);
        return 1;
    }
    $content = file_get_contents($path);
    $pattern = "/const CACHE_NAME = 'pos-cache-v(\d+)';/";
    if (!preg_match($pattern, $content, $m)) {
        $this->error('Could not find CACHE_NAME in service worker.');
        return 1;
    }
    $oldVersion = (int) $m[1];
    $newVersion = $oldVersion + 1;
    $newContent = preg_replace($pattern, "const CACHE_NAME = 'pos-cache-v{$newVersion}';", $content);
    file_put_contents($path, $newContent, LOCK_EX);
    $this->info("PWA cache version bumped: v{$oldVersion} → v{$newVersion}");
    $this->info('Users will get fresh assets on next page load. Close all app tabs and reopen, or hard refresh (Ctrl+Shift+R).');
    return 0;
})->purpose('Bump PWA service worker cache version to force browser cache clear');

// Generate application key and write to .env
Artisan::command('app:generate-key', function () {
    Artisan::call('key:generate', ['--force' => true]);
    $this->info(Artisan::output());
    $this->info('Application key generated and written to .env');
})->purpose('Generate application key and write to .env file');
