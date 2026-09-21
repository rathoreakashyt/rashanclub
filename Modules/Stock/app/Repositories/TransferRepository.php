<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\Transfer;
use Modules\Stock\Models\TransferDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransferRepository
{
    protected $model;
    protected $detailModel;

    public function __construct(
        Transfer $model,
        TransferDetail $detailModel
    ) {
        $this->model = $model;
        $this->detailModel = $detailModel;
    }

    /**
     * Get all transfers for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->with(['fromOutlet', 'toOutlet', 'user'])
            ->live()
            ->forCompany($companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get transfers with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->with(['fromOutlet', 'toOutlet', 'user'])
            ->live()
            ->forCompany($companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('fromOutlet', function ($q) use ($search) {
                        $q->where('outlet_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('toOutlet', function ($q) use ($search) {
                        $q->where('outlet_name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();
        $statusCounts = (clone $baseQuery)->select('status')->get()->groupBy('status')->map->count();

        $transfers = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'statusCounts' => $statusCounts,
            'data' => $transfers,
        ];
    }

    /**
     * Find transfer by ID
     */
    public function find(int $id): ?Transfer
    {
        return $this->model
            ->with(['fromOutlet', 'toOutlet', 'user', 'transferDetails.item'])
            ->live()
            ->find($id);
    }

    /**
     * Find transfer by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Transfer
    {
        try {
            $decryptedId = decrypt($encryptedId);
            return $this->find($decryptedId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new transfer with details
     */
    public function create(array $data): Transfer
    {
        return DB::transaction(function () use ($data) {
            // Create the main transfer
            $transfer = $this->model->create($data['transfer']);

            // Create transfer details
            foreach ($data['details'] as $detail) {
                $detail['transfer_id'] = $transfer->id;
                $this->detailModel->create($detail);
            }

            return $transfer->load(['fromOutlet', 'toOutlet', 'user', 'transferDetails.item']);
        });
    }

    /**
     * Update a transfer
     */
    public function update(Transfer $transfer, array $data): bool
    {
        return DB::transaction(function () use ($transfer, $data) {
            // Update main transfer
            $transfer->update($data['transfer']);

            // Hard delete existing details
            $this->detailModel
                ->where('transfer_id', $transfer->id)
                ->delete();

            // Create new details
            foreach ($data['details'] as $detail) {
                $detail['transfer_id'] = $transfer->id;
                $this->detailModel->create($detail);
            }

            return true;
        });
    }

    /**
     * Soft delete a transfer
     */
    public function delete(Transfer $transfer): bool
    {
        return DB::transaction(function () use ($transfer) {
            // Mark details as deleted
            $this->detailModel
                ->where('transfer_id', $transfer->id)
                ->update(['del_status' => 'Deleted']);

            // Mark main transfer as deleted
            return $transfer->update(['del_status' => 'Deleted']);
        });
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber(): string
    {
        $companyId = session('company.company_id');
        $prefix = 'TRF';
        $year = date('Y');
        
        // Get the last transfer for this company in this year
        $lastTransfer = $this->model
            ->forCompany($companyId)
            ->where('reference_no', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastTransfer) {
            // Extract the number part
            $parts = explode('-', $lastTransfer->reference_no);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }
}

