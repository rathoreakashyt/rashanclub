<?php

namespace App\Http\Controllers;

use App\Services\PwaSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class PwaController extends Controller
{
    public function __construct(
        protected PwaSettingService $pwaSettingService
    ) {}

    /**
     * Serve the service worker with Service-Worker-Allowed header for root scope.
     */
    public function serviceWorker(): \Illuminate\Http\Response
    {
        $path = public_path('pwa/serviceworker.js');
        if (!File::exists($path)) {
            abort(404);
        }
        $content = File::get($path);
        return response($content, 200, [
            'Content-Type' => 'application/javascript',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Serve the PWA manifest.json (public, used by browser for install).
     */
    public function manifest(Request $request): JsonResponse
    {
        $companyId = session('company.company_id');
        if (!$companyId && $request->has('company_id')) {
            $companyId = (int) $request->get('company_id');
        }
        $companyId = $companyId ?? 1;

        $manifest = $this->pwaSettingService->getManifestContent($companyId);

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }
}
