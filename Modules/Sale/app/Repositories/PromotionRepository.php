<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

class PromotionRepository
{
    protected $model;

    public function __construct(Promotion $model)
    {
        $this->model = $model;
    }

    /**
     * Get all promotions for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get promotions with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $promotions = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $promotions,
        ];
    }

    /**
     * Find promotion by ID
     */
    public function find(int $id): ?Promotion
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Find promotion by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Promotion
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Create a new promotion
     */
    public function create(array $data): Promotion
    {
        return $this->model->create($data);
    }

    /**
     * Update a promotion
     */
    public function update(Promotion $promotion, array $data): bool
    {
        return $promotion->update($data);
    }

    /**
     * Soft delete a promotion (mark as deleted)
     */
    public function delete(Promotion $promotion): bool
    {
        return $promotion->update(['del_status' => 'Deleted']);
    }

    /**
     * Check for overlapping promotions
     */
    public function checkOverlappingPromotions(int $companyId, int $outletId, string $startDate, string $endDate, ?int $foodMenuId = null, ?int $excludeId = null, ?int $type = null, ?string $startTime = null, ?string $endTime = null): bool
    {
        $query = $this->model
            ->where('status', '1')
            ->where('del_status', 'Live')
            ->where('outlet_id', $outletId)
            ->where('company_id', $companyId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->where(function($subQ) use ($startDate, $endDate) {
                    $subQ->where('start_date', '>=', $startDate)
                        ->where('start_date', '<=', $endDate);
                })
                ->orWhere(function($subQ) use ($startDate, $endDate) {
                    $subQ->where('end_date', '>=', $startDate)
                        ->where('end_date', '<=', $endDate);
                });
            });

        // Check time overlap if times are provided
        if ($startTime && $endTime) {
            $query->where(function($q) use ($startTime, $endTime) {
                $q->whereNull('start_time')
                  ->orWhere(function($subQ) use ($startTime, $endTime) {
                      $subQ->where('start_time', '>=', $startTime)
                           ->where('start_time', '<=', $endTime);
                  })
                  ->orWhere(function($subQ) use ($startTime, $endTime) {
                      $subQ->where('end_time', '>=', $startTime)
                           ->where('end_time', '<=', $endTime);
                  });
            });
        }

        if ($type === 2) {
            $query->where('type', 2);
        }

        if ($foodMenuId !== null) {
            $query->where('item_id', $foodMenuId);
        }

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}

