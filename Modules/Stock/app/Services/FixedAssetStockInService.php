<?php

namespace Modules\Stock\Services;

use Modules\Stock\Repositories\FixedAssetStockInRepository;
use Modules\Stock\Models\FixedAssetStockIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class FixedAssetStockInService
{
    protected $stockInRepository;

    public function __construct(FixedAssetStockInRepository $stockInRepository)
    {
        $this->stockInRepository = $stockInRepository;
    }

    /**
     * Get all stock ins for the current company
     */
    public function getAllStockIns(): Collection
    {
        return $this->stockInRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for stock ins listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->stockInRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($stockIn, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $stockIn->id,
                'reference_no' => $stockIn->reference_no,
                'date' => formatDate($stockIn->date),
                'grand_total' => formatAmount($stockIn->grand_total),
                'encrypted_id' => $stockIn->encrypted_id,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['filteredCount'],
            'data' => $transformedData,
        ];
    }

    /**
     * Get stock in by encrypted ID
     */
    public function getStockInByEncryptedId(string $encryptedId): ?FixedAssetStockIn
    {
        return $this->stockInRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new stock in
     */
    public function createStockIn(array $data): FixedAssetStockIn
    {
        try {
            \Log::info('FixedAssetStockInService::createStockIn - Input Data', ['data' => $data]);
            
            $preparedData = $this->prepareStockInData($data);
            
            \Log::info('FixedAssetStockInService::createStockIn - Prepared Data', ['prepared' => $preparedData]);
            
            $stockIn = $this->stockInRepository->create($preparedData);
            
            \Log::info('FixedAssetStockInService::createStockIn - Success', ['id' => $stockIn->id]);
            
            return $stockIn;
        } catch (\Exception $e) {
            \Log::error('FixedAssetStockInService::createStockIn - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing stock in
     */
    public function updateStockIn(FixedAssetStockIn $stockIn, array $data): bool
    {
        try {
            \Log::info('FixedAssetStockInService::updateStockIn - Input Data', ['id' => $stockIn->id, 'data' => $data]);
            
            $preparedData = $this->prepareStockInData($data, $stockIn);
            
            \Log::info('FixedAssetStockInService::updateStockIn - Prepared Data', ['prepared' => $preparedData]);
            
            $result = $this->stockInRepository->update($stockIn, $preparedData);
            
            \Log::info('FixedAssetStockInService::updateStockIn - Success', ['id' => $stockIn->id]);
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('FixedAssetStockInService::updateStockIn - Error', [
                'id' => $stockIn->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a stock in
     */
    public function deleteStockIn(FixedAssetStockIn $stockIn): bool
    {
        return $this->stockInRepository->delete($stockIn);
    }

    /**
     * Generate reference number
     */
    public function generateReferenceNumber(): string
    {
        return $this->stockInRepository->generateReferenceNumber();
    }

    /**
     * Prepare stock in data for storage
     */
    protected function prepareStockInData(array $data, ?FixedAssetStockIn $stockIn = null): array
    {
        $companyId = $this->getCompanyId();
        $userId = Auth::id();

        // Calculate grand total from item totals
        $grandTotal = 0;
        $items = $data['items'] ?? [];
        $quantities = $data['quantity'] ?? [];
        $unitPrices = $data['unit_price'] ?? [];
        $totals = $data['total'] ?? [];

        // Calculate grand total from item totals
        foreach ($totals as $total) {
            if (!empty($total)) {
                $grandTotal += (float) $total;
            }
        }

        // Determine reference number - preserve existing during update
        $referenceNo = $data['reference_no'] ?? null;
        if (empty($referenceNo)) {
            if ($stockIn) {
                // Preserve existing reference_no during update
                $referenceNo = $stockIn->reference_no;
            } else {
                // Generate new reference_no for create
                $referenceNo = $this->generateReferenceNumber();
            }
        }

        // Prepare main stock in data
        $stockInData = [
            'date' => $data['date'] ?? now()->toDateString(),
            'reference_no' => $referenceNo,
            'note' => $data['note'] ?? null,
            'grand_total' => $grandTotal,
            'user_id' => $userId,
            'company_id' => $companyId,
            'del_status' => 'Live',
        ];

        // Prepare stock in details - filter out empty items
        $details = [];
        foreach ($items as $index => $itemId) {
            if (!empty($itemId) && isset($quantities[$index]) && isset($unitPrices[$index]) && isset($totals[$index])) {
                $quantity = (int) ($quantities[$index] ?? 1);
                $unitPrice = (float) ($unitPrices[$index] ?? 0);
                $total = (float) ($totals[$index] ?? 0);
                
                // Only add if all values are valid
                if ($quantity > 0 && $unitPrice >= 0 && $total >= 0) {
                    $details[] = [
                        'item_id' => (int)$itemId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $total,
                        'user_id' => $userId,
                        'company_id' => $companyId,
                        'del_status' => 'Live',
                    ];
                }
            }
        }

        // Validate that we have at least one detail
        if (empty($details)) {
            throw new \Exception('At least one valid item with quantity, unit price, and total is required.');
        }

        return [
            'stock_in' => $stockInData,
            'details' => $details,
        ];
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }
}

