<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));


if (!defined('LP')) {
    $lp = getenv('LP') ?: '';
    if ($lp === '' && file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.env')) {
        $envContent = @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '.env');
        if ($envContent !== false && preg_match('/^\s*LP\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/m', $envContent, $m)) {
            $lp = trim($m[1]);
        }
    }
    define('LP', $lp);
}


if (!defined('LP_BD_FAVICON_LOGO_DATA_URI')) {
    define('LP_BD_FAVICON_LOGO_DATA_URI', 'data:image/png;base64,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');
}



/*
|--------------------------------------------------------------------------
| Ensure .env exists and APP_KEY is set so Laravel can boot
|--------------------------------------------------------------------------
| If .env is missing, create from .env.example. If APP_KEY is empty, set one
| so the app can boot. Use the web installer at /install to complete setup.
*/

$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';

$envNeedsWrite = false;
if (!file_exists($envPath)) {
    $examplePath = __DIR__ . DIRECTORY_SEPARATOR . '.env.example';
    if (is_file($examplePath)) {
        $content = file_get_contents($examplePath);
        $content = preg_replace('/^\s*DB_CONNECTION=.*/m', 'DB_CONNECTION=mysql', $content);
        $content = preg_replace('/^\s*APP_KEY=.*/m', 'APP_KEY=base64:' . base64_encode(random_bytes(32)), $content);
        $content = preg_replace('/^\s*SESSION_DRIVER=.*/m', 'SESSION_DRIVER=file', $content);
        $content = preg_replace('/^\s*CACHE_STORE=.*/m', 'CACHE_STORE=file', $content);
        $content = preg_replace('/^\s*QUEUE_CONNECTION=.*/m', 'QUEUE_CONNECTION=sync', $content);
        if (strpos($content, 'APP_INSTALLED=') === false) {
            $content .= "\nAPP_INSTALLED=false\n";
        }
        @file_put_contents($envPath, $content);
    }
} else {
    $content = @file_get_contents($envPath);
    if ($content !== false && !preg_match('/^\s*APP_KEY\s*=\s*.+\s*$/m', $content)) {
        $key = 'base64:' . base64_encode(random_bytes(32));
        if (preg_match('/^\s*APP_KEY\s*=/m', $content)) {
            $content = preg_replace('/^\s*APP_KEY\s*=.*/m', 'APP_KEY=' . $key, $content);
        } else {
            $content = trim($content) . "\nAPP_KEY=" . $key . "\n";
        }
        @file_put_contents($envPath, $content);
    }
}





























// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
