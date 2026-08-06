<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\WarrantyRepository;
use Modules\Sale\Models\Warranty;

class WarrantyService
{
    protected $warrantyRepository;

    public function __construct(WarrantyRepository $warrantyRepository)
    {
        $this->warrantyRepository = $warrantyRepository;
    }

    /**
     * Get all warranties for a company.
     */
    public function getAllWarranties(int $companyId, int $perPage = 15)
    {
        return $this->warrantyRepository->getAllForCompany($companyId, $perPage);
    }

    /**
     * Get a warranty by ID with its relationships.
     */
    public function getWarrantyById(int $id)
    {
        return $this->warrantyRepository->getByIdWithRelations($id);
    }

    /**
     * Get a warranty by encrypted ID with its relationships.
     */
    public function getWarrantyByEncryptedId(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            return $this->warrantyRepository->getByIdWithRelations((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new warranty.
     */
    public function createWarranty(array $data)
    {
        // Set default values
        $data['company_id'] = $data['company_id'] ?? session('company.company_id');
        $data['outlet_id'] = $data['outlet_id'] ?? session('outlet.outlet_id');
        $data['user_id'] = $data['user_id'] ?? auth()->id();

        // If customer_id is provided, fetch customer details
        if (isset($data['customer_id']) && $data['customer_id']) {
            $customer = \Modules\Sale\Models\Customer::find($data['customer_id']);
            if ($customer) {
                $data['customer_name'] = $data['customer_name'] ?? $customer->name;
                $data['customer_mobile'] = $data['customer_mobile'] ?? $customer->phone;
            }
        }

        return $this->warrantyRepository->create($data);
    }

    /**
     * Update a warranty.
     */
    public function updateWarranty(string $encryptedId, array $data)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid warranty ID');
        }

        // If customer_id is provided, fetch customer details
        if (isset($data['customer_id']) && $data['customer_id']) {
            $customer = \Modules\Sale\Models\Customer::find($data['customer_id']);
            if ($customer) {
                $data['customer_name'] = $data['customer_name'] ?? $customer->name;
                $data['customer_mobile'] = $data['customer_mobile'] ?? $customer->phone;
            }
        }

        return $this->warrantyRepository->update((int)$id, $data);
    }

    /**
     * Delete a warranty.
     */
    public function deleteWarranty(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid warranty ID');
        }
        
        $warranty = Warranty::find((int)$id);
        if (!$warranty) {
            throw new \Exception('Warranty not found');
        }
        
        return $this->warrantyRepository->delete((int)$id);
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->warrantyRepository->getDataTableData($params);
    }

    /**
     * Update warranty status
     */
    public function updateStatus(string $encryptedId, string $status)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid warranty ID');
        }

        // Validate status
        $validStatuses = ['R_F_C', 'S_T_V', 'R_T_V', 'D_T_C'];
        if (!in_array($status, $validStatuses)) {
            throw new \Exception('Invalid status');
        }

        return $this->warrantyRepository->updateStatus((int)$id, $status);
    }

    /**
     * Search product by IMEI/Serial number
     */
    public function searchProductByImeiSerial(string $imeiSerial)
    {
        $companyId = session('company.company_id');
        
        // Trim and clean the IMEI/Serial number
        $imeiSerial = trim($imeiSerial);
        
        if (empty($imeiSerial)) {
            return null;
        }
        
        // Search in sale_details for the IMEI/Serial (exact match, case-insensitive)
        $saleDetail = \Modules\Sale\Models\SaleDetail::whereRaw('LOWER(TRIM(expiry_imei_serial)) = ?', [strtolower($imeiSerial)])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->with(['sale.customer', 'item'])
            ->first();

        if (!$saleDetail || !$saleDetail->sale) {
            return null;
        }

        $sale = $saleDetail->sale;
        $customer = $sale->customer;
        $item = $saleDetail->item;

        return [
            'customer_id' => $customer ? $customer->id : null,
            'customer_name' => $customer ? $customer->name : null,
            'customer_mobile' => $customer ? $customer->phone : null,
            'product_name' => $item ? $item->name : null,
            'product_serial_no' => $saleDetail->expiry_imei_serial ?? $imeiSerial,
        ];
    }

    /**
     * Search sale by IMEI/Serial or Invoice Number for warranty checking
     * - IMEI/Serial: returns single product (unique)
     * - Invoice: returns all products on that invoice that have warranty/guarantee
     */
    public function searchForWarrantyCheck(string $searchType, string $searchValue)
    {
        $companyId = session('company.company_id');
        $searchValue = trim($searchValue);

        if (empty($searchValue)) {
            return null;
        }

        if ($searchType === 'imei') {
            return $this->searchByImeiSerial($searchValue);
        }

        if ($searchType === 'invoice') {
            return $this->searchByInvoice($searchValue);
        }

        return null;
    }

    /**
     * Search by IMEI/Serial – returns single product (IMEI/Serial is unique)
     */
    protected function searchByImeiSerial(string $searchValue)
    {
        $companyId = session('company.company_id');

        $saleDetail = \Modules\Sale\Models\SaleDetail::whereRaw('LOWER(TRIM(expiry_imei_serial)) = ?', [strtolower($searchValue)])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->with(['sale.customer', 'sale.outlet', 'sale.company', 'item'])
            ->first();

        if (!$saleDetail || !$saleDetail->sale) {
            return null;
        }

        $sale = $saleDetail->sale;
        $customer = $sale->customer;
        $item = $saleDetail->item;

        $warrantyPeriod = $this->buildWarrantyPeriod($item, $sale->sale_date);
        $guaranteePeriod = $this->buildGuaranteePeriod($item, $sale->sale_date);

        return [
            'search_type' => 'imei',
            'encrypted_sale_id' => encrypt($sale->id),
            'sale_no' => $sale->sale_no,
            'sale_date' => $sale->sale_date ? formatDate($sale->sale_date) : null,
            'customer_name' => $customer ? $customer->name : 'Walk-in Customer',
            'customer_mobile' => $customer ? $customer->phone : null,
            'customer_address' => $customer ? $customer->address : null,
            'product_name' => $item ? $item->name : ($saleDetail ? 'N/A' : null),
            'product_code' => $item ? $item->code : null,
            'imei_serial' => $saleDetail->expiry_imei_serial ?? null,
            'warranty' => $item ? ($item->warranty ? $item->warranty . ' ' . ($item->warranty_date ?? '') : 'N/A') : 'N/A',
            'warranty_period' => $warrantyPeriod ?? 'N/A',
            'guarantee' => $item ? ($item->guarantee ? $item->guarantee . ' ' . ($item->guarantee_date ?? '') : 'N/A') : 'N/A',
            'guarantee_period' => $guaranteePeriod ?? 'N/A',
        ];
    }

    /**
     * Search by Invoice – returns all products on that invoice (with warranty/guarantee info)
     */
    protected function searchByInvoice(string $searchValue)
    {
        $companyId = session('company.company_id');

        $sale = \Modules\Sale\Models\Sale::where('sale_no', $searchValue)
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->with(['customer', 'outlet', 'company', 'saleDetails.item'])
            ->first();

        if (!$sale || !$sale->saleDetails || $sale->saleDetails->isEmpty()) {
            return null;
        }

        $customer = $sale->customer;
        $products = [];

        foreach ($sale->saleDetails as $saleDetail) {
            $item = $saleDetail->item;
            $warrantyPeriod = $this->buildWarrantyPeriod($item, $sale->sale_date);
            $guaranteePeriod = $this->buildGuaranteePeriod($item, $sale->sale_date);

            $products[] = [
                'product_name' => $item ? $item->name : 'N/A',
                'product_code' => $item ? $item->code : null,
                'imei_serial' => $saleDetail->expiry_imei_serial ?? 'N/A',
                'warranty' => $item ? ($item->warranty ? $item->warranty . ' ' . ($item->warranty_date ?? '') : 'N/A') : 'N/A',
                'warranty_period' => $warrantyPeriod ?? 'N/A',
                'guarantee' => $item ? ($item->guarantee ? $item->guarantee . ' ' . ($item->guarantee_date ?? '') : 'N/A') : 'N/A',
                'guarantee_period' => $guaranteePeriod ?? 'N/A',
            ];
        }

        return [
            'search_type' => 'invoice',
            'encrypted_sale_id' => encrypt($sale->id),
            'sale_no' => $sale->sale_no,
            'sale_date' => $sale->sale_date ? formatDate($sale->sale_date) : null,
            'customer_name' => $customer ? $customer->name : 'Walk-in Customer',
            'customer_mobile' => $customer ? $customer->phone : null,
            'customer_address' => $customer ? $customer->address : null,
            'products' => $products,
        ];
    }

    /**
     * Build warranty period string for an item and sale date
     */
    protected function buildWarrantyPeriod($item, $saleDate)
    {
        if (
            !$item ||
            !$item->warranty ||
            !$item->warranty_date ||
            !$saleDate
        ) {
            return null;
        }

        $warrantyValue = (int) $item->warranty;
        $warrantyUnit  = $item->warranty_date; // day | month | year

        $warrantyExpiry = clone $saleDate;

        switch ($warrantyUnit) {
            case 'day':
                $warrantyExpiry->addDays($warrantyValue);
                break;

            case 'month':
                $warrantyExpiry->addMonths($warrantyValue);
                break;

            case 'year':
                $warrantyExpiry->addYears($warrantyValue);
                break;

            default:
                return null;
        }

        // Proper singular / plural handling
        $unitLabel = $warrantyValue === 1
            ? $warrantyUnit
            : $warrantyUnit . 's';

        return formatDate($saleDate) . ' - ' .
            formatDate($warrantyExpiry) . ' (' .
            $warrantyValue . ' ' . $unitLabel . ')';
    }


    /**
     * Build guarantee period string for an item and sale date
     */
    protected function buildGuaranteePeriod($item, $saleDate)
    {
        if (!$item || !$item->guarantee || !$item->guarantee_date || !$saleDate) {
            return null;
        }
        $guaranteeValue = (int) $item->guarantee;
        $guaranteeUnit = $item->guarantee_date;
        $guaranteeExpiry = clone $saleDate;
        switch ($guaranteeUnit) {
            case 'day':
                $guaranteeExpiry->addDays($guaranteeValue);
                break;
            case 'month':
                $guaranteeExpiry->addMonths($guaranteeValue);
                break;
            case 'year':
                $guaranteeExpiry->addYears($guaranteeValue);
                break;
            default:
                return null;
        }

        // Proper singular / plural handling
        $unitLabel = $guaranteeValue === 1
            ? $guaranteeUnit
            : $guaranteeUnit . 's';

        return formatDate($saleDate) . ' - ' .
            formatDate($guaranteeExpiry) . ' (' .
            $guaranteeValue . ' ' . $unitLabel . ')';
    }
}
