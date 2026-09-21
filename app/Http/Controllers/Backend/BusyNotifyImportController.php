<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\BusyNotifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Sale\Models\Customer;
use Modules\Stock\Models\Item;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleDetail;

class BusyNotifyImportController extends Controller
{
    /**
     * Show the BusyNotify import page.
     */
    public function index()
    {
        $isConfigured = BusyNotifyService::isEnabled();

        // Auto-detect companyId if not configured
        $companyId = config('services.busynotify.company_id');
        $companyName = '';
        if (!$companyId && $isConfigured) {
            try {
                $service = new BusyNotifyService();
                $companies = $service->getCompanies();
                if (!empty($companies[0])) {
                    $companyId = $companies[0]['companyId'];
                    $companyName = $companies[0]['companyName'] ?? '';
                }
            } catch (\Exception $e) {
                // Silently fail on index page
            }
        }

        return view('backend.busy-import.index', compact('isConfigured', 'companyId', 'companyName'));
    }

    /**
     * Test the BusyNotify API connection.
     */
    public function testConnection()
    {
        if (!BusyNotifyService::isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'BusyNotify API key not configured. Set BUSYNOTIFY_API_KEY in .env file.',
            ], 400);
        }

        try {
            $service = new BusyNotifyService();

            // First get companies to discover companyId
            $companies = $service->getCompanies();
            if (empty($companies)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No companies found. Check your API key.',
                ], 400);
            }

            $company = $companies[0];
            $companyId = $company['companyId'];
            $companyName = $company['companyName'] ?? 'Unknown';

            // Set companyId and fetch customers to verify
            $service->setCompanyId($companyId);
            $customers = $service->getCustomers();

            return response()->json([
                'success'      => true,
                'message'      => "Connected to \"{$companyName}\" (ID: {$companyId})",
                'company_id'   => $companyId,
                'company_name' => $companyName,
                'records_found' => count($customers),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import customers from BusyNotify API.
     */
    public function importCustomers(Request $request)
    {
        $companyId = session('company.company_id');
        $userId    = auth()->id();

        try {
            $service  = new BusyNotifyService();
            $busyData = $service->getCustomers();
            $imported = 0;
            $updated  = 0;
            $skipped  = 0;
            $errors   = [];

            foreach ($busyData as $row) {
                try {
                    $mapped = BusyNotifyService::mapCustomer($row);
                    $mapped['company_id'] = $companyId;
                    $mapped['user_id']    = $userId;

                    // Skip if no name
                    if (empty($mapped['name'])) {
                        $skipped++;
                        continue;
                    }

                    // Skip non-customer accounts (bank, expense, tax, etc.)
                    $groupName = $row['group_name'] ?? '';
                    if (in_array($groupName, [
                        'Bank Accounts', 'Duties & Taxes', 'Expenses (Indirect/Admn.)',
                        'Income (Indirect)', 'Current Assets', 'Fixed Assets',
                        'Unsecured Loans', 'PAIDUP CAPITAL',
                    ])) {
                        $skipped++;
                        continue;
                    }

                    // Check for existing customer by busy_id first (fastest dedup)
                    $existing = null;
                    if (!empty($mapped['busy_id'])) {
                        $existing = Customer::where('busy_id', $mapped['busy_id'])
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                    }

                    // Fallback: match by phone
                    if (!$existing && !empty($mapped['phone'])) {
                        $existing = Customer::where('phone', $mapped['phone'])
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                    }

                    // Fallback: match by name
                    if (!$existing && !empty($mapped['name'])) {
                        $existing = Customer::where('name', $mapped['name'])
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                    }

                    if ($existing) {
                        $existing->update(array_filter($mapped, fn($v) => $v !== null));
                        $updated++;
                    } else {
                        Customer::create($mapped);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row error: " . $e->getMessage();
                }
            }

            return response()->json([
                'success'    => true,
                'message'    => "Customers imported successfully!",
                'imported'   => $imported,
                'updated'    => $updated,
                'skipped'    => $skipped,
                'total_rows' => count($busyData),
                'errors'     => array_slice($errors, 0, 10),
            ]);
        } catch (\Exception $e) {
            Log::error('BusyNotify customer import failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import products/items from BusyNotify API.
     */
    public function importProducts(Request $request)
    {
        $companyId = session('company.company_id');
        $userId    = auth()->id();

        try {
            $service  = new BusyNotifyService();
            $busyData = $service->getProducts();
            $imported = 0;
            $updated  = 0;
            $skipped  = 0;
            $errors   = [];
            $now      = now()->toDateTimeString();

            foreach ($busyData as $row) {
                try {
                    $mapped = BusyNotifyService::mapProduct($row);
                    $mapped['company_id'] = $companyId;
                    $mapped['user_id']    = $userId;

                    // Skip if no name
                    if (empty($mapped['name'])) {
                        $skipped++;
                        continue;
                    }

                    // product_group → item_categories (lookup/auto-create)
                    $catId = $this->resolveCategory($companyId, $userId, $now, $row['product_group_name'] ?? null);
                    if ($catId) {
                        $mapped['category_id'] = $catId;
                    }

                    // unit text ("Pcs.") → units table ID (desktop compat)
                    $unitId = $this->resolveUnit($companyId, $userId, $now, $row['product_unit'] ?? null);
                    if ($unitId) {
                        $mapped['unit_type'] = (string) $unitId;
                        $mapped['sale_unit_id'] = $unitId;
                    }

                    // Check for existing item by busy_id first
                    $existing = null;
                    if (!empty($mapped['busy_id'])) {
                        $existing = Item::where('busy_id', $mapped['busy_id'])
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                    }

                    // Fallback: match by code (barcode/alias)
                    if (!$existing && !empty($mapped['code']) && $mapped['code'] !== $mapped['name']) {
                        $existing = Item::where('code', $mapped['code'])
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                    }

                    // Fallback: match by name
                    if (!$existing && !empty($mapped['name'])) {
                        $existing = Item::where('name', $mapped['name'])
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                    }

                    if ($existing) {
                        $existing->update(array_filter($mapped, fn($v) => $v !== null));
                        $updated++;
                    } else {
                        Item::create($mapped);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row error: " . $e->getMessage();
                }
            }

            return response()->json([
                'success'    => true,
                'message'    => "Products imported successfully!",
                'imported'   => $imported,
                'updated'    => $updated,
                'skipped'    => $skipped,
                'total_rows' => count($busyData),
                'errors'     => array_slice($errors, 0, 10),
            ]);
        } catch (\Exception $e) {
            Log::error('BusyNotify product import failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busy product_group_name → item_categories.category_id
     * (lookup by name, auto-create if missing).
     */
    private function resolveCategory(int $companyId, int $userId, string $now, ?string $groupName): ?int
    {
        $groupName = trim((string) $groupName);
        if ($groupName === '') return null;

        static $cache = [];
        $key = $companyId . '|' . mb_strtolower($groupName);
        if (isset($cache[$key])) return $cache[$key];

        $id = DB::table('item_categories')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('name', $groupName)
            ->value('id');

        if (!$id) {
            $id = DB::table('item_categories')->insertGetId([
                'name' => $groupName,
                'description' => 'BusyNotify import (product group)',
                'sort_id' => 0,
                'company_id' => $companyId,
                'user_id' => $userId,
                'del_status' => 'Live',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $cache[$key] = (int) $id;
    }

    /**
     * Busy unit text ("Pcs.") → units table ID (lookup/auto-create).
     */
    private function resolveUnit(int $companyId, int $userId, string $now, ?string $unitText): ?int
    {
        $unitText = trim((string) $unitText);
        if ($unitText === '') return null;

        static $cache = [];
        $key = $companyId . '|' . mb_strtolower($unitText);
        if (isset($cache[$key])) return $cache[$key];

        $id = DB::table('units')
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where(function ($q) use ($unitText) {
                $q->where('name', $unitText)->orWhere('short_name', $unitText)->orWhere('unit_name', $unitText);
            })
            ->value('id');

        if (!$id) {
            $id = DB::table('units')->insertGetId([
                'name' => $unitText,
                'short_name' => mb_substr($unitText, 0, 10),
                'unit_name' => $unitText,
                'company_id' => $companyId,
                'user_id' => $userId,
                'del_status' => 'Live',
                'base_unit_id' => null,
                'conversion_rate' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $cache[$key] = (int) $id;
    }

    /**
     * Import bills/invoices from BusyNotify API (via sales-register endpoint).
     */
    public function importBills(Request $request)
    {
        $companyId = session('company.company_id');
        $userId    = auth()->id();

        try {
            $service  = new BusyNotifyService();
            $busyData = $service->getSalesRegister();
            $imported = 0;
            $updated  = 0;
            $skipped  = 0;
            $errors   = [];

            foreach ($busyData as $row) {
                try {
                    $mapped = BusyNotifyService::mapSale($row);
                    $mapped['company_id'] = $companyId;
                    $mapped['user_id']    = $userId;

                    // Skip if no invoice number
                    if (empty($mapped['invoice_no'])) {
                        $skipped++;
                        continue;
                    }

                    // Find the customer linked to this bill
                    $customerName  = $row['customer_name'] ?? null;
                    $customerPhone = $row['customer_mobile'] ?? null;
                    $customerId    = null;

                    if ($customerPhone) {
                        $cust = Customer::where('phone', $customerPhone)
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                        $customerId = $cust?->id;
                    }

                    if (!$customerId && $customerName) {
                        $cust = Customer::where('name', $customerName)
                            ->where('company_id', $companyId)
                            ->where('del_status', 'Live')
                            ->first();
                        $customerId = $cust?->id;
                    }

                    $mapped['customer_id'] = $customerId;

                    // Check if bill already exists
                    $existing = Sale::where('invoice_no', $mapped['invoice_no'])
                        ->where('company_id', $companyId)
                        ->where('del_status', 'Live')
                        ->first();

                    if ($existing) {
                        $existing->update(array_filter($mapped, fn($v) => $v !== null));
                        $updated++;
                    } else {
                        Sale::create($mapped);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row error: " . $e->getMessage();
                }
            }

            return response()->json([
                'success'    => true,
                'message'    => "Bills imported successfully!",
                'imported'   => $imported,
                'updated'    => $updated,
                'skipped'    => $skipped,
                'total_rows' => count($busyData),
                'errors'     => array_slice($errors, 0, 10),
            ]);
        } catch (\Exception $e) {
            Log::error('BusyNotify bill import failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import all data types at once.
     */
    public function importAll(Request $request)
    {
        $results = [];

        // Import Customers
        $customerResult = $this->importCustomers($request);
        $results['customers'] = $customerResult->getData(true);

        // Import Products
        $productResult = $this->importProducts($request);
        $results['products'] = $productResult->getData(true);

        // Import Bills
        $billResult = $this->importBills($request);
        $results['bills'] = $billResult->getData(true);

        $totalImported = ($results['customers']['imported'] ?? 0)
                       + ($results['products']['imported'] ?? 0)
                       + ($results['bills']['imported'] ?? 0);

        $totalUpdated = ($results['customers']['updated'] ?? 0)
                      + ($results['products']['updated'] ?? 0)
                      + ($results['bills']['updated'] ?? 0);

        return response()->json([
            'success' => true,
            'message' => "All data imported! Total: {$totalImported} new, {$totalUpdated} updated.",
            'results' => $results,
            'summary' => [
                'customers_imported' => $results['customers']['imported'] ?? 0,
                'products_imported'  => $results['products']['imported'] ?? 0,
                'bills_imported'     => $results['bills']['imported'] ?? 0,
                'customers_updated'  => $results['customers']['updated'] ?? 0,
                'products_updated'   => $results['products']['updated'] ?? 0,
                'bills_updated'      => $results['bills']['updated'] ?? 0,
            ],
        ]);
    }

    // ─── Token Management ─────────────────────────────────────

    /**
     * Save BusyNotify auth token to DB (companies table).
     */
    public function saveToken(Request $request)
    {
        $request->validate([
            'busynotify_token' => 'required|string|min:10|max:500',
        ]);

        $companyId = session('company.company_id');

        try {
            DB::table('companies')
                ->where('id', $companyId)
                ->update([
                    'busynotify_token'      => trim($request->busynotify_token),
                    'busynotify_company_id' => null, // reset so it auto-detects on next test
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Token saved successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('BusyNotify saveToken failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save token: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current token config (masked) for display.
     */
    public function getTokenConfig()
    {
        $companyId = session('company.company_id');
        $row = DB::table('companies')
            ->where('id', $companyId)
            ->select('busynotify_token', 'busynotify_company_id')
            ->first();

        $token = $row->busynotify_token ?? null;
        $masked = $token ? substr($token, 0, 6) . str_repeat('*', max(0, strlen($token) - 10)) . substr($token, -4) : null;

        return response()->json([
            'has_token'    => !empty($token),
            'masked_token' => $masked,
            'company_id'   => $row->busynotify_company_id ?? null,
        ]);
    }
}
