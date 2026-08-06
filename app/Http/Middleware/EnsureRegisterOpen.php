<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Sale\Models\Register;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisterOpen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');
        $companyId = session('company.company_id');

        // Check if outlet is set
        if (!$outletId) {
            return redirect()->route('outlet.index')->with('error', 'Please select an outlet first.');
        }

        // Skip register check for register routes themselves
        if ($request->is('register/*')) {
            return $next($request);
        }

        // Check if user has an open register
        // Register is open if:
        // 1. A record exists, AND
        // 2. Latest record has register_status = 1 (open)
        // Register is closed if:
        // 1. No record exists, OR
        // 2. Latest record has register_status = 2 (closed)
        $registerOpen = Register::isUserRegisterOpen($userId, $outletId, $companyId);

        // If register is already open, allow access (user can continue working)
        // This handles the case where user logged out without closing register
        // and logged in again - they should be able to continue with the open register
        if ($registerOpen) {
            return $next($request);
        }

        // Register is closed - redirect to register page to open it
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'register_closed' => true,
                'message' => 'Register is closed. Please open a register first.',
                'redirect_url' => route('register.create')
            ], 403);
        }

        // Store intended URL for redirect after opening register
        session()->put('url.intended', $request->fullUrl());

        // Redirect to register page
        return redirect()->route('register.create')
            ->with('error', 'Please open a register to continue.');
    }
}
