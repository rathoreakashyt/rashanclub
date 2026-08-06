<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoModeRestriction
{
    /**
     * Path patterns that are restricted from update in demo mode.
     * (Profile, password update, settings - user-specific or system configuration)
     * Note: Excludes forgot-password, reset-password (guest flows).
     */
    protected array $restrictedUpdatePatterns = [
        'outlet.store',
        'outlet.update',
        'outlet.destroy',
        'user/profile',
        'business-setting',
        'pos-setting',
        'pos-layout',
        'tax-setting',
        'invoice-setting',
        'email-setting',
        'test-email',
        'test-sms',
        'test-whatsapp',
        'whitelabel-setting',
        'whatsapp-setting',
        'payment-setting',
        'sms-setting',
        'zatca-setting',
        'zatca-generate-directory',
        'zatca-generate-csr',
    ];

    /**
     * Exact path matches for restricted updates (e.g. password update at /password).
     */
    protected array $restrictedExactPaths = [
        'password', // PUT password.update - authenticated user's password change
    ];

    /**
     * Handle an incoming request.
     * When IS_DEMO is true: block all DELETE, and block PUT/PATCH/POST for profile, password, settings.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.is_demo', false)) {
            return $next($request);
        }

        $method = strtoupper($request->method());

        // Block all DELETE requests in demo mode
        if ($method === 'DELETE') {
            return $this->demoModeResponse($request, 'delete');
        }

        // Block PUT, PATCH, POST for profile, password, and settings
        if (in_array($method, ['PUT', 'PATCH', 'POST'])) {
            $path = $request->path();
            $routeName = $request->route()?->getName() ?? '';

            // Exact path match (e.g. /password for password update)
            if (in_array($path, $this->restrictedExactPaths)) {
                return $this->demoModeResponse($request, 'update');
            }

            // Pattern match for profile, settings, etc.
            foreach ($this->restrictedUpdatePatterns as $pattern) {
                if (str_contains($path, $pattern) || str_contains($routeName, $pattern)) {
                    return $this->demoModeResponse($request, 'update');
                }
            }
        }

        return $next($request);
    }

    /**
     * Return appropriate response for demo mode restriction.
     */
    protected function demoModeResponse(Request $request, string $action): Response
    {
        $message = __('This information could not be updated or deleted in Demo Mode.');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect' => route('demo-mode.restricted'),
            ], 403);
        }

        return redirect()
            ->route('demo-mode.restricted')
            ->with('demo_mode_message', $message)
            ->with('demo_mode_action', $action);
    }
}
