<?php

namespace Modules\Purchase\Repositories;

use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseDetail;
use Modules\Purchase\Models\PurchasePayment;
use Modules\Stock\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseRepository
{
    /**
     * Get all purchases for a company with pagination.
     */
    public function getAllForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return Purchase::with([
                'supplier', 
                'purchaseDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseDetails.item.parent'
            ])
            ->live()
            ->forCompany($companyId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a purchase by ID with its details.
     */
    public function getByIdWithDetails(int $id): ?Purchase
    {
        return Purchase::with([
                'supplier', 
                'purchaseDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseDetails.item.parent', 
                'creator'
            ])
            ->live()
            ->find($id);
    }

    /**
     * Create a new purchase with its details.
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            // Create the purchase
            $purchase = Purchase::create($data['purchase']);
            
            // Create purchase details
            foreach ($data['items'] as $item) {
                $purchase->purchaseDetails()->create($item);
            }
            
            // Update items: last_purchase_price and last_three_purchase_avg
            $this->updateItemsPurchasePrices($purchase->id, $data['items']);
            
            // Reload purchase with only live purchase details
            return $purchase->fresh()->load([
                'supplier', 
                'purchaseDetails' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'purchaseDetails.item.parent'
            ]);
        });
    }

    /**
     * Update a purchase and its details.
     */
    public function update(int $id, array $data): Purchase
    {
        return DB::transaction(function () use ($id, $data) {
            $purchase = $this->getByIdWithDetails($id);
            
            if (!$purchase) {
                throw new \Exception('Purchase not found');
            }
            
            // Update the purchase
            $purchase->update($data['purchase']);
            
            // Hard delete existing purchase details (remove records from database)
            PurchaseDetail::where('purchase_id', $id)->delete();
            
            // Create new purchase details
            foreach ($data['items'] as $item) {
                $purchase->purchaseDetails()->create($item);
            }
            
            // Update items: last_purchase_price and last_three_purchase_avg
            $this->updateItemsPurchasePrices($id, $data['items']);
            
            // Reload purchase with only live purchase details
            return $purchase->fresh()->load(['supplier', 'purchaseDetails' => function($query) {
                $query->where('del_status', 'Live');
            }, 'purchaseDetails.item.parent']);
        });
    }

    /**
     * Delete a purchase.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $purchase = Purchase::live()->find($id);
            
            if (!$purchase) {
                throw new \Exception('Purchase not found');
            }
            
            // Mark purchase as deleted
            $purchase->update(['del_status' => 'Deleted']);
            
            // Mark purchase details as deleted
            $purchase->purchaseDetails()->update(['del_status' => 'Deleted']);
            
            // Mark purchase payments as deleted
            $purchase->purchasePayments()->update(['del_status' => 'Deleted']);
            
            return true;
        });
    }

    /**
     * Generate a unique reference number for a purchase.
     */
    public function generateReferenceNumber(int $companyId): string
    {
        $prefix = 'PUR-' . date('Y') . '-';
        $lastPurchase = Purchase::where('reference_no', 'like', $prefix . '%')
            ->forCompany($companyId)
            ->orderBy('reference_no', 'desc')
            ->first();
        
        if ($lastPurchase) {
            $lastNumber = (int) substr($lastPurchase->reference_no, strlen($prefix));
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
            $exists = PurchaseDetail::whereHas('item', function ($query) use ($itemId) {
                    $query->where('id', $itemId);
                })
                ->where('del_status', 'Live')
                ->whereJsonContains('imei_serial_numbers', $number)
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
     * @param int|null $excludePurchaseId Optional purchase ID to exclude from the check (for updates)
     * @return bool
     */
    public function checkImeiSerialExists(int $itemId, string $imeiSerial, ?int $excludePurchaseId = null): bool
    {
        $companyId = session('company.company_id');
        
        $query = PurchaseDetail::where('item_id', $itemId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('expiry_imei_serial', $imeiSerial);
        
        // Exclude the current purchase if provided (for updates)
        if ($excludePurchaseId !== null) {
            $query->where('purchase_id', '!=', $excludePurchaseId);
        }
        
        return $query->exists();
    }

    /**
     * Create purchase payments for a purchase.
     */
    public function createPurchasePayments(int $purchaseId, array $payments): void
    {
        foreach ($payments as $payment) {
            PurchasePayment::create([
                'purchase_id' => $purchaseId,
                'payment_id' => $payment['payment_id'],
                'amount' => $payment['amount'],
                'user_id' => auth()->id(),
                'outlet_id' => session('outlet.outlet_id'),
                'company_id' => session('company.company_id'),
                'del_status' => 'Live'
            ]);
        }
    }

    /**
     * Update purchase paid amount based on payments.
     */
    public function updatePurchasePaidAmount(int $purchaseId): void
    {
        $purchase = Purchase::find($purchaseId);
        if (!$purchase) {
            return;
        }

        $totalPaid = PurchasePayment::where('purchase_id', $purchaseId)
            ->where('del_status', 'Live')
            ->sum('amount');

        $purchase->update([
            'paid' => $totalPaid,
            'due_amount' => $purchase->grand_total - $totalPaid
        ]);
    }

    /**
     * Get all payments for a purchase.
     */
    public function getPurchasePayments(int $purchaseId)
    {
        return PurchasePayment::with(['paymentMethod', 'user'])
            ->where('purchase_id', $purchaseId)
            ->live()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Delete a purchase payment.
     */
    public function deletePurchasePayment(int $paymentId): bool
    {
        $payment = PurchasePayment::live()->find($paymentId);
        
        if (!$payment) {
            throw new \Exception('Payment not found');
        }
        
        // Mark payment as deleted
        $payment->update(['del_status' => 'Deleted']);
        
        // Update purchase paid amount
        $this->updatePurchasePaidAmount($payment->purchase_id);
        
        return true;
    }

    /**
     * Update items table with last_purchase_price and last_three_purchase_avg after purchase save.
     *
     * @param int $purchaseId The purchase ID just saved
     * @param array $items The items array from purchase data (each has item_id, unit_price)
     */
    public function updateItemsPurchasePrices(int $purchaseId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        // Build map: item_id => [unit_prices in this purchase] (for same item multiple lines we average)
        $itemPrices = [];
        foreach ($items as $row) {
            $itemId = (int) $row['item_id'];
            $unitPrice = (float) ($row['unit_price'] ?? 0);
            if (!isset($itemPrices[$itemId])) {
                $itemPrices[$itemId] = [];
            }
            $itemPrices[$itemId][] = $unitPrice;
        }

        foreach ($itemPrices as $itemId => $prices) {
            $lastPurchasePrice = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;

            // Last 3 purchase avg: get last 3 purchase_ids for this item, then average of (avg unit_price per purchase)
            $lastThreePurchaseIds = PurchaseDetail::where('item_id', $itemId)
                ->where('del_status', 'Live')
                ->select('purchase_id')
                ->groupBy('purchase_id')
                ->orderByRaw('MAX(purchase_id) DESC')
                ->limit(3)
                ->pluck('purchase_id');

            $lastThreePurchaseAvg = 0;
            if ($lastThreePurchaseIds->isNotEmpty()) {
                $avgsPerPurchase = PurchaseDetail::where('item_id', $itemId)
                    ->where('del_status', 'Live')
                    ->whereIn('purchase_id', $lastThreePurchaseIds)
                    ->selectRaw('purchase_id, AVG(unit_price) as avg_price')
                    ->groupBy('purchase_id')
                    ->pluck('avg_price');
                $lastThreePurchaseAvg = $avgsPerPurchase->avg();
            }

            Item::where('id', $itemId)->update([
                'last_purchase_price' => $lastPurchasePrice,
                'last_three_purchase_avg' => $lastThreePurchaseAvg,
            ]);
        }
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

        $query = Purchase::with(['supplier'])
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('invoice_no', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = Purchase::where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->count();

        $filteredCount = $query->count();

        $purchases = $query->skip($start)
            ->take($length)
            ->get();

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        $transformedData = $purchases->map(function ($purchase, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $purchase->id,
                'reference_no' => $purchase->reference_no,
                'supplier_name' => $purchase->supplier ? $purchase->supplier->name . ' (' . $purchase->supplier->phone . ')' : null,
                'date' => formatDate($purchase->date),
                'supplier_invoice_no' => $purchase->invoice_no,
                'grand_total' => formatAmount($purchase->grand_total),
                'due_amount' => formatAmount($purchase->due_amount),
                'encrypted_id' => $purchase->encrypted_id
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
