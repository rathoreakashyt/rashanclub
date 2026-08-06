<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Quotation;
use Modules\Sale\Models\QuotationDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QuotationRepository
{
    protected $model;
    protected $detailModel;

    public function __construct(
        Quotation $model,
        QuotationDetail $detailModel
    ) {
        $this->model = $model;
        $this->detailModel = $detailModel;
    }

    /**
     * Get all quotations for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->with(['customer', 'user'])
            ->live()
            ->forCompany($companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get quotations with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->with(['customer', 'user'])
            ->live()
            ->forCompany($companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $quotations = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $quotations,
        ];
    }

    /**
     * Find quotation by ID
     */
    public function find(int $id): ?Quotation
    {
        return $this->model
            ->with(['customer', 'user', 'quotationDetails.item'])
            ->live()
            ->find($id);
    }

    /**
     * Find quotation by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Quotation
    {
        try {
            $decryptedId = decrypt($encryptedId);
            return $this->find($decryptedId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new quotation with details
     */
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            // Create the main quotation
            $quotation = $this->model->create($data['quotation']);

            // Create quotation details
            foreach ($data['details'] as $detail) {
                $detail['quotation_id'] = $quotation->id;
                $this->detailModel->create($detail);
            }

            return $quotation->load(['customer', 'user', 'quotationDetails.item']);
        });
    }

    /**
     * Update a quotation
     */
    public function update(Quotation $quotation, array $data): bool
    {
        return DB::transaction(function () use ($quotation, $data) {
            // Update main quotation
            $quotation->update($data['quotation']);

            // Hard delete existing details
            $this->detailModel
                ->where('quotation_id', $quotation->id)
                ->delete();

            // Create new details
            foreach ($data['details'] as $detail) {
                $detail['quotation_id'] = $quotation->id;
                $this->detailModel->create($detail);
            }

            return true;
        });
    }

    /**
     * Soft delete a quotation
     */
    public function delete(Quotation $quotation): bool
    {
        return DB::transaction(function () use ($quotation) {
            // Mark details as deleted
            $this->detailModel
                ->where('quotation_id', $quotation->id)
                ->update(['del_status' => 'Deleted']);

            // Mark main quotation as deleted
            return $quotation->update(['del_status' => 'Deleted']);
        });
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber(): string
    {
        $companyId = session('company.company_id');
        $prefix = 'QT';
        $year = date('Y');
        
        // Get the last quotation for this company in this year
        $lastQuotation = $this->model
            ->forCompany($companyId)
            ->where('reference_no', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastQuotation) {
            // Extract the number part
            $parts = explode('-', $lastQuotation->reference_no);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }
}

