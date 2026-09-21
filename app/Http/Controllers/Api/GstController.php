<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GstValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GST/HSN Validation API Controller.
 * Provides GSTIN validation (offline checksum + online Razorpay lookup),
 * HSN code validation, HSN search, and Indian states list.
 */
class GstController extends Controller
{
    public function __construct(
        private GstValidationService $gstService
    ) {}

    /**
     * GET /api/gst/validate/{gstin}
     * Validate GSTIN with offline checksum + online Razorpay lookup.
     * Caches result in gst_cache table.
     */
    public function validateGstin(string $gstin): JsonResponse
    {
        $gstin = strtoupper(trim($gstin));

        // Step 1: Offline checksum validation
        $offlineResult = $this->gstService->validateGstin($gstin);
        if (!$offlineResult['valid']) {
            return response()->json($offlineResult, 400);
        }

        // Step 2: Check cache (avoid hitting external APIs repeatedly)
        $cached = $this->getCachedGstin($gstin);
        if ($cached) {
            return response()->json([
                'valid' => true,
                'message' => 'Valid GSTIN',
                'gstin' => $gstin,
                'state_code' => $offlineResult['state_code'],
                'state_name' => $offlineResult['state_name'],
                'pan' => $offlineResult['pan'],
                'entity_type' => $offlineResult['entity_type'],
                'online' => [
                    'legal_name' => $cached->legal_name ?? '',
                    'trade_name' => $cached->trade_name ?? '',
                    'status' => $cached->status ?? '',
                    'address' => $cached->address ?? '',
                ],
                'cached' => true,
            ]);
        }

        // Step 3: Online lookup via Razorpay
        $onlineData = $this->razorpayLookup($gstin);

        // Step 4: Fallback to local customers/suppliers
        if (empty($onlineData['legal_name'])) {
            $onlineData = $this->localLookup($gstin);
        }

        // Step 5: Cache the result
        if (!empty($onlineData['legal_name'])) {
            $this->cacheGstinResult($gstin, $onlineData);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Valid GSTIN',
            'gstin' => $gstin,
            'state_code' => $offlineResult['state_code'],
            'state_name' => $offlineResult['state_name'],
            'pan' => $offlineResult['pan'],
            'entity_type' => $offlineResult['entity_type'],
            'online' => $onlineData,
            'cached' => false,
        ]);
    }

    /**
     * GET /api/hsn/validate/{code}
     * Validate HSN code and return description + GST rate.
     */
    public function validateHsn(string $code): JsonResponse
    {
        $result = $this->gstService->validateHsn($code);

        return response()->json($result, $result['valid'] ? 200 : 400);
    }

    /**
     * GET /api/hsn/search?q={query}
     * Search HSN codes by keyword or code prefix.
     */
    public function searchHsn(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $limit = min((int) $request->query('limit', 20), 50);

        if (trim($query) === '') {
            return response()->json(['results' => []]);
        }

        $results = $this->gstService->searchHsn($query, $limit);

        return response()->json(['results' => $results]);
    }

    /**
     * GET /api/gst/states
     * Get all Indian states with GST codes.
     */
    public function states(): JsonResponse
    {
        return response()->json(['states' => $this->gstService->getAllStates()]);
    }

    // ═══════════════════ PRIVATE HELPERS ═══════════════════

    /**
     * Check gst_cache table for recent cached result.
     */
    private function getCachedGstin(string $gstin): ?object
    {
        try {
            $cached = DB::table('gst_cache')->where('gstin', $gstin)->first();
            if ($cached && ($cached->updated_at ?? '') > now()->subDays(30)->toDateTimeString()) {
                return $cached;
            }
        } catch (\Throwable) {}

        return null;
    }

    /**
     * Scrape Razorpay GST Search (free, no API key needed).
     */
    private function razorpayLookup(string $gstin): array
    {
        $legalName = '';
        $tradeName = '';
        $status = '';
        $address = '';

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://razorpay.com/gst-number-search/{$gstin}/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml',
                ],
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && strlen($html) > 1000) {
                if (preg_match('/Legal Name of Business.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $legalName = trim(strip_tags($m[1]));
                if (preg_match('/GSTIN Status.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $status = trim(strip_tags($m[1]));
                if (preg_match('/State Jurisdiction.*?<h5[^>]*>(.*?)<\/h5>/s', $html, $m))
                    $address = trim(strip_tags($m[1]));
                // Trade name from page title
                if (preg_match('/GST Number of\s+(.*?)\s+is\s+' . preg_quote($gstin, '/') . '/i', $html, $tm))
                    $tradeName = trim($tm[1]);
            }
        } catch (\Throwable $e) {
            \Log::warning("Razorpay GST scrape failed for {$gstin}: " . $e->getMessage());
        }

        return [
            'legal_name' => $legalName,
            'trade_name' => $tradeName,
            'status' => $status,
            'address' => $address,
        ];
    }

    /**
     * Fallback: check local customers/suppliers tables.
     */
    private function localLookup(string $gstin): array
    {
        $user = request()->user();
        $companyId = (int) ($user->company_id ?? 1);

        $customer = DB::table('customers')
            ->where('gst_number', $gstin)
            ->where('company_id', $companyId)
            ->first();

        if ($customer) {
            return [
                'legal_name' => $customer->name ?? '',
                'trade_name' => '',
                'status' => 'Active (local)',
                'address' => $customer->address ?? '',
            ];
        }

        $supplier = DB::table('suppliers')
            ->where('gst_number', $gstin)
            ->where('company_id', $companyId)
            ->first();

        if ($supplier) {
            return [
                'legal_name' => $supplier->name ?? '',
                'trade_name' => '',
                'status' => 'Active (local)',
                'address' => $supplier->address ?? '',
            ];
        }

        return ['legal_name' => '', 'trade_name' => '', 'status' => '', 'address' => ''];
    }

    /**
     * Cache GSTIN lookup result in gst_cache table.
     */
    private function cacheGstinResult(string $gstin, array $data): void
    {
        try {
            DB::table('gst_cache')->updateOrInsert(
                ['gstin' => $gstin],
                [
                    'legal_name' => $data['legal_name'] ?? '',
                    'trade_name' => $data['trade_name'] ?? '',
                    'status' => $data['status'] ?? '',
                    'address' => $data['address'] ?? '',
                    'updated_at' => now()->toDateTimeString(),
                ]
            );
        } catch (\Throwable) {}
    }
}
