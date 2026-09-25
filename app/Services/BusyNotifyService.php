<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BusyNotify API Service
 * 
 * Integrates with BusyNotify REST API to import data from BUSY Accounting Software.
 * API Docs: https://documenter.getpostman.com/view/16351567/2sBXiriTqN
 * 
 * IMPORTANT: All endpoints use POST method with body params:
 *   - authToken (required) — your BusyNotify API token
 *   - companyId (required) — BUSY company ID
 *   - financialYear (required) — 4-digit year like 2026
 * 
 * Endpoints:
 *   - /v1/companies     → List companies (get companyId)
 *   - /v1/customers     → Customer master data
 *   - /v1/products      → Product/item catalog
 *   - /v1/sales-register → Sales invoices
 */
class BusyNotifyService
{
    private string $baseUrl;
    protected string $apiKey;
    protected ?int $companyId;
    private string $financialYear;
    /** Last response ka metadata (rowCount etc.) — truncation check ke liye. */
    private array $lastMeta = [];

    public function __construct(?string $tokenOverride = null)
    {
        $this->baseUrl       = rtrim(config('services.busynotify.base_url', 'https://api.busynotify.in'), '/');
        $this->financialYear = config('services.busynotify.financial_year', date('Y'));

        // Priority: override > DB > .env
        if ($tokenOverride !== null) {
            $this->apiKey    = $tokenOverride;
            $this->companyId = null;
        } else {
            [$dbToken, $dbCompanyId] = static::getFromDb();
            $this->apiKey    = $dbToken ?: config('services.busynotify.api_key', '');
            $this->companyId = $dbCompanyId
                ? (int) $dbCompanyId
                : (config('services.busynotify.company_id') ? (int) config('services.busynotify.company_id') : null);
        }
    }

    /**
     * Read token & company_id saved in companies table (DB-based config).
     */
    public static function getFromDb(): array
    {
        try {
            $companyId = session('company.company_id', 1);
            $row = \DB::table('companies')
                ->where('id', $companyId)
                ->select('busynotify_token', 'busynotify_company_id')
                ->first();
            return [
                $row->busynotify_token ?? null,
                $row->busynotify_company_id ?? null,
            ];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /**
     * Check if the service is configured (DB token takes priority).
     */
    public static function isEnabled(): bool
    {
        [$dbToken] = static::getFromDb();
        return !empty($dbToken) || !empty(config('services.busynotify.api_key'));
    }

    /**
     * Set company ID at runtime.
     */
    public function setCompanyId(int $id): void
    {
        $this->companyId = $id;
    }

    // ─── Generic HTTP helpers ────────────────────────────────

    /**
     * POST request to BusyNotify API.
     * All BusyNotify endpoints use POST with body params.
     */
    protected function post(string $endpoint, array $extraParams = []): array
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('BusyNotify API key not configured. Set BUSYNOTIFY_API_KEY in .env');
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $payload = array_merge([
            'authToken'      => $this->apiKey,
            'companyId'      => $this->companyId,
            'financialYear'  => $this->financialYear,
        ], $extraParams);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->retry(2, 2000)
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('BusyNotify API error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException("BusyNotify API error: HTTP {$response->status()} on {$endpoint}");
        }

        $json = $response->json();
        $this->lastMeta = is_array($json['metadata'] ?? null) ? $json['metadata'] : [];

        if (isset($json['success']) && $json['success'] === false) {
            throw new \RuntimeException('BusyNotify API: ' . ($json['message'] ?? 'Unknown error'));
        }

        return $json;
    }

    /**
     * Last /v1/* response ka metadata (e.g. rowCount).
     * Caller isse verify kar sakta hai ki rows truncate/paginate to nahi hui.
     */
    public function getLastMeta(): array
    {
        return $this->lastMeta;
    }

    // ─── Companies ───────────────────────────────────────────

    /**
     * Fetch list of companies from BusyNotify.
     * Used to discover companyId automatically.
     */
    public function getCompanies(): array
    {
        $result = $this->post('/v1/companies');
        return $result['data'] ?? [];
    }

    /**
     * Auto-detect companyId if not configured.
     * Returns the first (and usually only) company.
     */
    public function getCompanyId(): ?int
    {
        if ($this->companyId) {
            return $this->companyId;
        }

        $companies = $this->getCompanies();
        if (!empty($companies[0]['companyId'])) {
            return (int) $companies[0]['companyId'];
        }

        return null;
    }

    // ─── Customers ───────────────────────────────────────────

    /**
     * Fetch all customers from BusyNotify.
     * 
     * Actual response format:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "customer_id": 5894,
     *       "customer_name": "SHARMA TRADERS",
     *       "mobile_number": "9876543210",
     *       "email_id": "...",
     *       "address_line_1": "...",
     *       "address_line_2": "...",
     *       "pin_code": "110043",
     *       "country": "India",
     *       "state": "Delhi",
     *       "gst_number": "07...",
     *       "opening_balance": 0,
     *       "balance": 45000,
     *       "group_name": "Sundry Creditors"
     *     }
     *   ],
     *   "metadata": { "rowCount": 454, ... }
     * }
     */
    public function getCustomers(): array
    {
        $result = $this->post('/v1/customers');
        return $result['data'] ?? [];
    }

    /**
     * Map a BusyNotify customer record to the local `customers` table schema.
     */
    public static function mapCustomer(array $busy): array
    {
        // Build full address from address lines
        $addressParts = array_filter([
            $busy['address_line_1'] ?? null,
            $busy['address_line_2'] ?? null,
            $busy['address_line_3'] ?? null,
            $busy['address_line_4'] ?? null,
        ]);
        $fullAddress = implode(', ', $addressParts) ?: null;

        return [
            'name'               => $busy['customer_name'] ?? null,
            'phone'              => $busy['mobile_number'] ?? null,
            'email'              => $busy['email_id'] ?? null,
            'address'            => $fullAddress,
            'city'               => $busy['station'] ?? null,
            'state_id'           => null, // State name mapping done in controller
            'postal_code'        => $busy['pin_code'] ?? null,
            'country'            => $busy['country'] ?? 'India',
            'gst_number'         => $busy['gst_number'] ?? null,
            'opening_balance'    => $busy['opening_balance'] ?? 0,
            'opening_balance_type' => ($busy['opening_balance'] ?? 0) < 0 ? 'Receivable' : 'Payable',
            'credit_limit'       => 0,
            'description'        => $busy['customer_alias'] ?: null,
            'del_status'         => 'Live',
            // BusyNotify external ID for deduplication
            'busy_id'            => $busy['customer_id'] ?? null,
        ];
    }

    // ─── Products / Items ────────────────────────────────────

    /**
     * Fetch all products from BusyNotify.
     * 
     * Actual response format:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "product_id": 1542,
     *       "product_name": "1012 EVEREADY 1200 UP CARD PAC",
     *       "product_alias": "8901691000824",
     *       "product_sales_price": 18,
     *       "product_purchase_price": 13,
     *       "product_mrp": 20,
     *       "product_group_name": "Eveready",
     *       "product_unit": "Pcs.",
     *       "product_tax_name": "GST 18%",
     *       "product_hsn_code": "85061000",
     *       "product_tax_rate": 18,
     *       "product_stock": 96,
     *       ...
     *     }
     *   ],
     *   "metadata": { "rowCount": 1200, ... }
     * }
     */
    public function getProducts(): array
    {
        $result = $this->post('/v1/products');
        return $result['data'] ?? [];
    }

    /**
     * Map a BusyNotify product record to the local `items` table schema.
     */
    public static function mapProduct(array $busy): array
    {
        return [
            'name'              => $busy['product_name'] ?? null,
            'code'              => $busy['product_alias'] ?: ($busy['product_name'] ?? null),
            'alternative_name'  => $busy['product_print_name'] ?? null,
            'generic_name'      => $busy['product_group_name'] ?? null,
            'type'              => 'Standard',
            'purchase_price'    => $busy['product_purchase_price'] ?? 0,
            'sale_price'        => $busy['product_sales_price'] ?? 0,
            'mrp_price'         => $busy['product_mrp'] ?? 0,
            'whole_sale_price'  => $busy['product_sales_price'] ?? 0,
            'hsn_code'          => $busy['product_hsn_code'] ?? null,
            'description'       => $busy['product_description_line1'] ?? null,
            'alert_quantity'    => 0,
            'unit_type'         => $busy['product_unit'] ?? null,
            'enable_disable_status' => 1,
            'del_status'        => 'Live',
            'busy_id'           => $busy['product_id'] ?? null,
        ];
    }

    // ─── Sales Register (Bills/Invoices) ─────────────────────

    /**
     * Fetch sales register from BusyNotify.
     * 
     * Endpoint: /v1/sales-register
     * Returns sales invoices with items, taxes, and payment details.
     */
    public function getSalesRegister(array $params = []): array
    {
        $result = $this->post('/v1/sales-register', $params);
        return $result['data'] ?? [];
    }

    /**
     * Map a BusyNotify sales record to the local `sales` table schema.
     */
    public static function mapSale(array $busy): array
    {
        return [
            'invoice_no'    => $busy['voucher_number'] ?? $busy['invoice_no'] ?? $busy['bill_no'] ?? null,
            'sale_date'     => $busy['voucher_date'] ?? $busy['date'] ?? null,
            'sub_total'     => $busy['gross_amount'] ?? $busy['total'] ?? 0,
            'total_payable' => $busy['net_amount'] ?? $busy['total'] ?? 0,
            'grand_total'   => $busy['net_amount'] ?? $busy['total'] ?? 0,
            'paid_amount'   => $busy['paid_amount'] ?? 0,
            'due_amount'    => $busy['balance'] ?? 0,
            'disc'          => $busy['discount'] ?? 0,
            'vat'           => $busy['tax_amount'] ?? 0,
            'del_status'    => 'Live',
            'busy_id'       => $busy['voucher_id'] ?? $busy['id'] ?? null,
        ];
    }

    // ─── Stock Delta (Fast 2-min sync) ──────────────────────────

    /**
     * Fetch all products stock-only (lightweight).
     * Returns only product_id, product_alias (code), product_stock.
     * Used by stock-delta sync every 2 minutes.
     *
     * IMPORTANT: stock field missing/null ho to `null` return hota hai (0 nahi) —
     * caller "pata nahi" samajh kar us product ko chhod deta hai, warna API me
     * kisi bhi change (field rename/company mismatch) se sab stock zero ho jate.
     */
    public function getProductsStockOnly(): array
    {
        $result = $this->post('/v1/products');
        $data   = $result['data'] ?? [];

        // Sirf stock-related fields rakhein — memory/bandwidth save
        return array_map(fn($p) => [
            'product_id'    => $p['product_id'] ?? null,
            'product_alias' => $p['product_alias'] ?? null,
            'product_name'  => $p['product_name'] ?? null,
            'product_stock' => ($p['product_stock'] ?? null) === null ? null : $p['product_stock'],
        ], $data);
    }

    // ─── Ledgers / Customer Pending Bills ────────────────────

    /**
     * Fetch customer pending bills.
     */
    public function getCustomerPendingBills(array $params = []): array
    {
        $result = $this->post('/v1/customers-pending-bills', $params);
        return $result['data'] ?? [];
    }

    /**
     * Fetch customer ledger.
     */
    public function getCustomerLedger(array $params = []): array
    {
        $result = $this->post('/v1/customer-ledger', $params);
        return $result['data'] ?? [];
    }
}
