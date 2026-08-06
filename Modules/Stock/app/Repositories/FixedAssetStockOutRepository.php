<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\FixedAssetStockOut;
use Modules\Stock\Models\FixedAssetStockOutDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FixedAssetStockOutRepository
{
    protected $model;
    protected $detailModel;

    public function __construct(
        FixedAssetStockOut $model,
        FixedAssetStockOutDetail $detailModel
    ) {
        $this->model = $model;
        $this->detailModel = $detailModel;
    }

    /**
     * Get all stock outs for a company
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
     * Get stock outs with pagination and search for DataTable
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

        $stockOuts = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $stockOuts,
        ];
    }

    /**
     * Find stock out by ID
     */
    public function find(int $id): ?FixedAssetStockOut
    {
        return $this->model
            ->with(['user', 'stockOutDetails.fixedAssetItem'])
            ->live()
            ->find($id);
    }

    /**
     * Find stock out by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?FixedAssetStockOut
    {
        try {
            $decryptedId = decrypt($encryptedId);
            return $this->find($decryptedId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new stock out with details
     */
    public function create(array $data): FixedAssetStockOut
    {
        return DB::transaction(function () use ($data) {
            // Validate that details array is not empty
            if (empty($data['details']) || !is_array($data['details'])) {
                throw new \Exception('At least one item detail is required.');
            }

            // Create the main stock out
            $stockOut = $this->model->create($data['stock_out']);

            // Create stock out details
            foreach ($data['details'] as $detail) {
                $detail['asset_stock_out_id'] = $stockOut->id;
                $this->detailModel->create($detail);
            }

            return $stockOut->load(['user', 'stockOutDetails.fixedAssetItem']);
        });
    }

    /**
     * Update a stock out
     */
    public function update(FixedAssetStockOut $stockOut, array $data): bool
    {
        return DB::transaction(function () use ($stockOut, $data) {
            // Validate that details array is not empty
            if (empty($data['details']) || !is_array($data['details'])) {
                throw new \Exception('At least one item detail is required.');
            }

            // Update main stock out
            $stockOut->update($data['stock_out']);

            // Hard delete existing details
            $this->detailModel
                ->where('asset_stock_out_id', $stockOut->id)
                ->delete();

            // Create new details
            foreach ($data['details'] as $detail) {
                $detail['asset_stock_out_id'] = $stockOut->id;
                $this->detailModel->create($detail);
            }

            return true;
        });
    }

    /**
     * Soft delete a stock out
     */
    public function delete(FixedAssetStockOut $stockOut): bool
    {
        return DB::transaction(function () use ($stockOut) {
            // Mark details as deleted
            $this->detailModel
                ->where('asset_stock_out_id', $stockOut->id)
                ->update(['del_status' => 'Deleted']);

            // Mark main stock out as deleted
            return $stockOut->update(['del_status' => 'Deleted']);
        });
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber(): string
    {
        $companyId = session('company.company_id');
        $prefix = 'FA-SO';
        $year = date('Y');
        
        // Get the last stock out for this company in this year
        $lastStockOut = $this->model
            ->forCompany($companyId)
            ->where('reference_no', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastStockOut) {
            // Extract the number part
            $parts = explode('-', $lastStockOut->reference_no);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }
}

