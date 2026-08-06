<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\FixedAssetStockIn;
use Modules\Stock\Models\FixedAssetStockInDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FixedAssetStockInRepository
{
    protected $model;
    protected $detailModel;

    public function __construct(
        FixedAssetStockIn $model,
        FixedAssetStockInDetail $detailModel
    ) {
        $this->model = $model;
        $this->detailModel = $detailModel;
    }

    /**
     * Get all stock ins for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->with(['user'])
            ->live()
            ->forCompany($companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get stock ins with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->with(['user'])
            ->live()
            ->forCompany($companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $stockIns = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $stockIns,
        ];
    }

    /**
     * Find stock in by ID
     */
    public function find(int $id): ?FixedAssetStockIn
    {
        return $this->model
            ->with(['user', 'stockInDetails.fixedAssetItem'])
            ->live()
            ->find($id);
    }

    /**
     * Find stock in by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?FixedAssetStockIn
    {
        try {
            $decryptedId = decrypt($encryptedId);
            return $this->find($decryptedId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new stock in with details
     */
    public function create(array $data): FixedAssetStockIn
    {
        return DB::transaction(function () use ($data) {
            // Validate that details array is not empty
            if (empty($data['details']) || !is_array($data['details'])) {
                throw new \Exception('At least one item detail is required.');
            }
            // Create the main stock in
            $stockIn = $this->model->create($data['stock_in']);
            // Create stock in details
            foreach ($data['details'] as $detail) {
                $detail['asset_stock_in_id'] = $stockIn->id;
                $this->detailModel->create($detail);
            }
            return $stockIn->load(['user', 'stockInDetails.fixedAssetItem']);
        });
    }

    
    /**
     * Update a stock in
     */
    public function update(FixedAssetStockIn $stockIn, array $data): bool
    {
        return DB::transaction(function () use ($stockIn, $data) {
            // Validate that details array is not empty
            if (empty($data['details']) || !is_array($data['details'])) {
                throw new \Exception('At least one item detail is required.');
            }

            // Update main stock in
            $stockIn->update($data['stock_in']);

            // Hard delete existing details
            $this->detailModel
                ->where('asset_stock_in_id', $stockIn->id)
                ->delete();

            // Create new details
            foreach ($data['details'] as $detail) {
                $detail['asset_stock_in_id'] = $stockIn->id;
                $this->detailModel->create($detail);
            }

            return true;
        });
    }

    /**
     * Soft delete a stock in
     */
    public function delete(FixedAssetStockIn $stockIn): bool
    {
        return DB::transaction(function () use ($stockIn) {
            // Mark details as deleted
            $this->detailModel
                ->where('asset_stock_in_id', $stockIn->id)
                ->update(['del_status' => 'Deleted']);

            // Mark main stock in as deleted
            return $stockIn->update(['del_status' => 'Deleted']);
        });
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber(): string
    {
        $companyId = session('company.company_id');
        $prefix = 'FA-SI';
        $year = date('Y');
        
        // Get the last stock in for this company in this year
        $lastStockIn = $this->model
            ->forCompany($companyId)
            ->where('reference_no', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastStockIn) {
            // Extract the number part
            $parts = explode('-', $lastStockIn->reference_no);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }
}

