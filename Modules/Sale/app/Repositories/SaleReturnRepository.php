<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\SaleReturn;
use Modules\Sale\Models\SaleReturnDetail;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SaleReturnRepository
{
    /**
     * Get all sale returns for a company with pagination.
     */
    public function getAllForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return SaleReturn::with([
                'customer', 
                'sale',
                'paymentMethod',
                'saleReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleReturnDetails.item.parent'
            ])
            ->live()
            ->forCompany($companyId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a sale return by ID with its details.
     */
    public function getByIdWithDetails(int $id): ?SaleReturn
    {
        return SaleReturn::with([
                'customer', 
                'sale',
                'paymentMethod',
                'saleReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleReturnDetails.item.parent', 
                'user',
                'outlet',
                'company'
            ])
            ->live()
            ->find($id);
    }

    /**
     * Get a sale return by encrypted ID with its details.
     */
    public function getByEncryptedId(string $encryptedId): ?SaleReturn
    {
        try {
            $id = decrypt($encryptedId);
            return $this->getByIdWithDetails((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new sale return with its details.
     */
    public function create(array $data): SaleReturn
    {
        return DB::transaction(function () use ($data) {
            // Create the sale return
            $saleReturn = SaleReturn::create($data['sale_return']);
            
            // Create sale return details
            foreach ($data['items'] as $item) {
                $saleReturn->saleReturnDetails()->create($item);
            }
            
            // Reload sale return with only live details
            return $saleReturn->fresh()->load([
                'customer', 
                'sale',
                'paymentMethod',
                'saleReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleReturnDetails.item.parent'
            ]);
        });
    }

    /**
     * Update a sale return and its details.
     */
    public function update(int $id, array $data): SaleReturn
    {
        return DB::transaction(function () use ($id, $data) {
            $saleReturn = $this->getByIdWithDetails($id);
            
            if (!$saleReturn) {
                throw new \Exception('Sale return not found');
            }
            
            // Update the sale return
            $saleReturn->update($data['sale_return']);
            
            // Delete existing sale return details (only mark live ones as deleted)
            SaleReturnDetail::where('sale_return_id', $id)
                ->where('del_status', 'Live')
                ->update(['del_status' => 'Deleted']);
            
            // Create new sale return details
            foreach ($data['items'] as $item) {
                $saleReturn->saleReturnDetails()->create($item);
            }
            
            // Reload sale return with only live details
            return $saleReturn->fresh()->load([
                'customer', 
                'sale',
                'paymentMethod',
                'saleReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'saleReturnDetails.item.parent'
            ]);
        });
    }

    /**
     * Delete a sale return.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $saleReturn = SaleReturn::live()->find($id);
            
            if (!$saleReturn) {
                throw new \Exception('Sale return not found');
            }
            
            // Mark sale return as deleted
            $saleReturn->update(['del_status' => 'Deleted']);
            
            // Mark sale return details as deleted
            $saleReturn->saleReturnDetails()->update(['del_status' => 'Deleted']);
            
            return true;
        });
    }

    /**
     * Generate a unique reference number for a sale return.
     */
    public function generateReferenceNumber(int $companyId): string
    {
        $prefix = 'SR-' . date('Y') . '-';
        $lastSaleReturn = SaleReturn::where('reference_no', 'like', $prefix . '%')
            ->forCompany($companyId)
            ->orderBy('reference_no', 'desc')
            ->first();
        
        if ($lastSaleReturn) {
            $lastNumber = (int) substr($lastSaleReturn->reference_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get total returned quantity for an item from a specific sale.
     * This includes all return invoices for this item from this sale.
     * 
     * @param int $saleId The sale ID
     * @param int $itemId The item ID
     * @param int|null $excludeSaleReturnId Optional sale return ID to exclude (for updates)
     * @return float
     */
    public function getTotalReturnedQuantity(int $saleId, int $itemId, ?int $excludeSaleReturnId = null): float
    {
        $companyId = session('company.company_id');
        
        $query = SaleReturnDetail::where('sale_id', $saleId)
            ->where('item_id', $itemId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        // Exclude the current sale return if provided (for updates)
        if ($excludeSaleReturnId !== null) {
            $query->where('sale_return_id', '!=', $excludeSaleReturnId);
        }
        
        return (float) $query->sum('return_quantity_amount');
    }

    /**
     * Get total sold quantity for an item from a specific sale.
     * 
     * @param int $saleId The sale ID
     * @param int $itemId The item ID
     * @return float
     */
    public function getTotalSoldQuantity(int $saleId, int $itemId): float
    {
        $saleDetail = \Modules\Sale\Models\SaleDetail::where('sales_id', $saleId)
            ->where('item_id', $itemId)
            ->where('del_status', 'Live')
            ->first();
        
        if (!$saleDetail) {
            return 0;
        }
        
        return (float) $saleDetail->qty;
    }

    /**
     * Get available return quantity for an item from a specific sale.
     * This is the sold quantity minus already returned quantity.
     * 
     * @param int $saleId The sale ID
     * @param int $itemId The item ID
     * @param int|null $excludeSaleReturnId Optional sale return ID to exclude (for updates)
     * @return float
     */
    public function getAvailableReturnQuantity(int $saleId, int $itemId, ?int $excludeSaleReturnId = null): float
    {
        $soldQuantity = $this->getTotalSoldQuantity($saleId, $itemId);
        $returnedQuantity = $this->getTotalReturnedQuantity($saleId, $itemId, $excludeSaleReturnId);
        
        return max(0, $soldQuantity - $returnedQuantity);
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        $length = $params['length'] ?? 10;
        $start = $params['start'] ?? 0;
        $search = $params['search'] ?? '';
        $companyId = session('company.company_id');

        $query = SaleReturn::with(['customer', 'sale'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('sale', function($q) use ($search) {
                      $q->where('sale_no', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = SaleReturn::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();

        $filteredCount = $query->count();

        $saleReturns = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $saleReturns->map(function ($saleReturn, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $saleReturn->id,
                'reference_no' => $saleReturn->reference_no,
                'customer_name' => $saleReturn->customer ? $saleReturn->customer->name : null,
                'sale_no' => $saleReturn->sale ? $saleReturn->sale->sale_no : null,
                'date' => formatDate($saleReturn->date),
                'total_return_amount' => formatAmount($saleReturn->total_return_amount),
                'paid' => formatAmount($saleReturn->paid),
                'due' => formatAmount($saleReturn->due),
                'encrypted_id' => $saleReturn->encrypted_id
            ];
        });

        return [
            'draw' => $params['draw'] ?? 1,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData
        ];
    }
}
