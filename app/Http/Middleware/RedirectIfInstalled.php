<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function __construct(
        protected InstallerService $installer
    ) {}

    /**
     * Redirect to application if already installed (used on install routes).
     * Allow install/complete so the user can see the success page and credentials after install.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->installer->isInstalled()) {
            return $next($request);
        }

        // Let the complete page show after a fresh install (user can then click "Go to Login")
        if ($request->routeIs('install.complete')) {
            return $next($request);
        }

        return redirect()->route('login');
    }
}
