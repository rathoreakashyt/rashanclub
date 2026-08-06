<?php

namespace Modules\Stock\Services;

use Modules\Stock\Repositories\FixedAssetStockOutRepository;
use Modules\Stock\Models\FixedAssetStockOut;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class FixedAssetStockOutService
{
    protected $stockOutRepository;

    public function __construct(FixedAssetStockOutRepository $stockOutRepository)
    {
        $this->stockOutRepository = $stockOutRepository;
    }

    /**
     * Get all stock outs for the current company
     */
    public function getAllStockOuts(): Collection
    {
        return $this->stockOutRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for stock outs listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->stockOutRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($stockOut, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $stockOut->id,
                'reference_no' => $stockOut->reference_no,
                'date' => formatDate($stockOut->date),
                'grand_total' => formatAmount($stockOut->grand_total),
                'encrypted_id' => $stockOut->encrypted_id,
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
     * Get stock out by encrypted ID
     */
    public function getStockOutByEncryptedId(string $encryptedId): ?FixedAssetStockOut
    {
        return $this->stockOutRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new stock out
     */
    public function createStockOut(array $data): FixedAssetStockOut
    {
        try {
            \Log::info('FixedAssetStockOutService::createStockOut - Input Data', ['data' => $data]);
            
            $preparedData = $this->prepareStockOutData($data);
            
            \Log::info('FixedAssetStockOutService::createStockOut - Prepared Data', ['prepared' => $preparedData]);
            
            $stockOut = $this->stockOutRepository->create($preparedData);
            
            \Log::info('FixedAssetStockOutService::createStockOut - Success', ['id' => $stockOut->id]);
            
            return $stockOut;
        } catch (\Exception $e) {
            \Log::error('FixedAssetStockOutService::createStockOut - Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing stock out
     */
    public function updateStockOut(FixedAssetStockOut $stockOut, array $data): bool
    {
        try {
            \Log::info('FixedAssetStockOutService::updateStockOut - Input Data', ['id' => $stockOut->id, 'data' => $data]);
            
            $preparedData = $this->prepareStockOutData($data, $stockOut);
            
            \Log::info('FixedAssetStockOutService::updateStockOut - Prepared Data', ['prepared' => $preparedData]);
            
            $result = $this->stockOutRepository->update($stockOut, $preparedData);
            
            \Log::info('FixedAssetStockOutService::updateStockOut - Success', ['id' => $stockOut->id]);
            
            return $result;
        } catch (\Exception $e) {
            \Log::error('FixedAssetStockOutService::updateStockOut - Error', [
                'id' => $stockOut->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a stock out
     */
    public function deleteStockOut(FixedAssetStockOut $stockOut): bool
    {
        return $this->stockOutRepository->delete($stockOut);
    }

    /**
     * Generate reference number
     */
    public function generateReferenceNumber(): string
    {
        return $this->stockOutRepository->generateReferenceNumber();
    }

    /**
     * Prepare stock out data for storage
     */
    protected function prepareStockOutData(array $data, ?FixedAssetStockOut $stockOut = null): array
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
            if ($stockOut) {
                // Preserve existing reference_no during update
                $referenceNo = $stockOut->reference_no;
            } else {
                // Generate new reference_no for create
                $referenceNo = $this->generateReferenceNumber();
            }
        }

        // Prepare main stock out data
        $stockOutData = [
            'date' => $data['date'] ?? now()->toDateString(),
            'reference_no' => $referenceNo,
            'note' => $data['note'] ?? null,
            'grand_total' => $grandTotal,
            'user_id' => $userId,
            'company_id' => $companyId,
            'del_status' => 'Live',
        ];

        // Prepare stock out details - filter out empty items
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
            'stock_out' => $stockOutData,
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

