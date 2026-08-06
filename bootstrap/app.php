<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\EnsureOutletSet;
use App\Http\Middleware\EnsureRegisterOpen;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\Access\AuthorizationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Apply locale and demo mode middleware to web routes
        $middleware->web(append: [
            SetLocale::class,
            \App\Http\Middleware\DemoModeRestriction::class,
        ]);
        
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'outlet_set' => EnsureOutletSet::class,
            'register_open' => EnsureRegisterOpen::class,
            'locale' => SetLocale::class,
            'redirect.if.installed' => \App\Http\Middleware\RedirectIfInstalled::class,
            'redirect.if.not.installed' => \App\Http\Middleware\RedirectIfNotInstalled::class,
            'verify.installation.integrity' => \App\Http\Middleware\VerifyInstallationIntegrity::class,
            'demo.mode' => \App\Http\Middleware\DemoModeRestriction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : null;
            // Page Expired (419) - session/CSRF token expired, e.g. during logout or long-open login
            if ($code === 419) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => __('Your session has expired. Please refresh the page and try again.'),
                    ], 419);
                }
                return redirect()->guest(route('login'))
                    ->with('error', __('Your session has expired. Please log in again.'));
            }
            if ($code === 501) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Not Implemented'], 501)
                    : response()->view('errors.501', [], 501);
            }
            if ($code === 502) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Bad Gateway'], 502)
                    : response()->view('errors.502', [], 502);
            }
            $is403 = ($e instanceof HttpException && $e->getStatusCode() === 403)
                || $e instanceof AuthorizationException;
            if ($is403) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $e->getMessage()], 403);
                }
                if (Auth::check()) {
                    return response()->view('errors.403_layout', ['exception' => $e], 403);
                }
                return response()->view('errors.403', ['exception' => $e], 403);
            }
        });
    })->create();
