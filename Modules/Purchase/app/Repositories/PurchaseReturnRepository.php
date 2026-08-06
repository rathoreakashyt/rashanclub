<?php

namespace Modules\Purchase\Repositories;

use Modules\Purchase\Models\PurchaseReturn;
use Modules\Purchase\Models\PurchaseReturnDetail;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseReturnRepository
{
    /**
     * Get all purchase returns for a company with pagination.
     */
    public function getAllForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return PurchaseReturn::with([
                'supplier', 
                'purchaseReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseReturnDetails.item.parent'
            ])
            ->live()
            ->forCompany($companyId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a purchase return by ID with its details.
     */
    public function getByIdWithDetails(int $id): ?PurchaseReturn
    {
        return PurchaseReturn::with([
                'supplier', 
                'purchaseReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseReturnDetails.item.parent', 
                'creator'
            ])
            ->live()
            ->find($id);
    }

    /**
     * Create a new purchase return with its details.
     */
    public function create(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            // Create the purchase return
            $purchaseReturn = PurchaseReturn::create($data['purchase_return']);
            
            // Create purchase return details
            foreach ($data['items'] as $item) {
                $purchaseReturn->purchaseReturnDetails()->create($item);
            }
            
            return $purchaseReturn->load([
                'supplier', 
                'purchaseReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseReturnDetails.item.parent'
            ]);
        });
    }

    /**
     * Update a purchase return and its details.
     */
    public function update(int $id, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($id, $data) {
            $purchaseReturn = $this->getByIdWithDetails($id);
            
            if (!$purchaseReturn) {
                throw new \Exception('Purchase Return not found');
            }
            
            // Update the purchase return
            $purchaseReturn->update($data['purchase_return']);
            
            // Delete existing purchase return details (only mark live ones as deleted)
            PurchaseReturnDetail::where('pur_return_id', $id)
                ->where('del_status', 'Live')
                ->update(['del_status' => 'Deleted']);
            
            // Create new purchase return details
            foreach ($data['items'] as $item) {
                $purchaseReturn->purchaseReturnDetails()->create($item);
            }
            
            return $purchaseReturn->load([
                'supplier', 
                'purchaseReturnDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseReturnDetails.item.parent'
            ]);
        });
    }

    /**
     * Delete a purchase return.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $purchaseReturn = PurchaseReturn::live()->find($id);
            
            if (!$purchaseReturn) {
                throw new \Exception('Purchase Return not found');
            }
            
            // Mark purchase return as deleted
            $purchaseReturn->update(['del_status' => 'Deleted']);
            
            // Mark purchase return details as deleted
            $purchaseReturn->purchaseReturnDetails()->update(['del_status' => 'Deleted']);
            
            return true;
        });
    }

    /**
     * Generate a unique reference number for a purchase return.
     */
    public function generateReferenceNumber(int $companyId): string
    {
        $prefix = 'PRET-' . date('Y') . '-';
        $lastPurchaseReturn = PurchaseReturn::where('reference_no', 'like', $prefix . '%')
            ->forCompany($companyId)
            ->orderBy('reference_no', 'desc')
            ->first();
        
        if ($lastPurchaseReturn) {
            $lastNumber = (int) substr($lastPurchaseReturn->reference_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check if an item can be added multiple times based on its type.
     */
    public function canItemBeAddedMultipleTimes(int $itemId): bool
    {
        $item = \Modules\Stock\Models\Item::find($itemId);
        
        if (!$item) {
            return false;
        }
        
        return in_array($item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product']);
    }

    /**
     * Check for duplicate IMEI/Serial numbers for a specific item.
     */
    public function checkDuplicateImeiSerial(int $itemId, array $imeiSerialNumbers): array
    {
        $duplicates = [];
        
        foreach ($imeiSerialNumbers as $number) {
            $exists = PurchaseReturnDetail::whereHas('item', function ($query) use ($itemId) {
                    $query->where('id', $itemId);
                })
                ->where('del_status', 'Live')
                ->where('expiry_imei_serial', 'like', '%' . $number . '%')
                ->exists();
                
            if ($exists) {
                $duplicates[] = $number;
            }
        }
        
        return $duplicates;
    }

    /**
     * Check if a single IMEI/Serial number already exists for a specific item.
     * 
     * @param int $itemId The item ID
     * @param string $imeiSerial The IMEI/Serial number to check
     * @param int|null $excludePurchaseReturnId Optional purchase return ID to exclude from the check (for updates)
     * @return bool
     */
    public function checkImeiSerialExists(int $itemId, string $imeiSerial, ?int $excludePurchaseReturnId = null): bool
    {
        $companyId = session('company.company_id');
        
        $query = PurchaseReturnDetail::where('item_id', $itemId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('expiry_imei_serial', $imeiSerial);
        
        // Exclude the current purchase return if provided (for updates)
        if ($excludePurchaseReturnId !== null) {
            $query->where('pur_return_id', '!=', $excludePurchaseReturnId);
        }
        
        return $query->exists();
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

        $query = PurchaseReturn::with(['supplier'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('pur_ref_no', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = PurchaseReturn::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();

        $filteredCount = $query->count();

        $purchaseReturns = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $purchaseReturns->map(function ($purchaseReturn, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $purchaseReturn->id,
                'reference_no' => $purchaseReturn->reference_no,
                'supplier_name' => $purchaseReturn->supplier ? $purchaseReturn->supplier->name : null,
                'date' => formatDate($purchaseReturn->date),
                'purchase_date' => formatDate($purchaseReturn->purchase_date),
                'status' => $purchaseReturn->return_status,
                'grand_total' => formatAmount($purchaseReturn->total_return_amount),
                'encrypted_id' => $purchaseReturn->encrypted_id
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

