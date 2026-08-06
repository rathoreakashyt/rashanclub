<?php

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the 'down' command we will
| require this file to exist so that the maintenance page can be displayed correctly.
| We also check for the existence of a file in the public directory called
| 'down' to determine if we are in maintenance mode.
|
*/

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script so that we do not have to worry about the
| loading of our our classes "manually". Feels great to relax.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Shutdown The Application
|--------------------------------------------------------------------------
|
| Last thing to do is to shutdown the application and send the response
| back to the browser so that the user can see the output of the page.
|
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);

/*
|--------------------------------------------------------------------------
| Display The Execution Time
|--------------------------------------------------------------------------
|
| Display the amount of time it took to execute the current request from the
| LARAVEL_START constant to this point. This will give the user some
| indication of how long the application has been running.
|
*/

$executionTime = microtime(true) - LARAVEL_START;

/*
|--------------------------------------------------------------------------
| Display The Memory Usage
|--------------------------------------------------------------------------
|
| Display the amount of memory used to execute the current request from the
| LARAVEL_START constant to this point. This will give the user some
| indication of how much memory their application is using.
|
*/

$memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;

echo "<!-- Execution Time: {$executionTime} seconds -->\n";
echo "<!-- Memory Usage: {$memoryUsage} MB -->\n";
