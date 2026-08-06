<?php

namespace Modules\Purchase\Services;

use Modules\Purchase\Repositories\PurchaseReturnRepository;
use Modules\Purchase\Http\Requests\PurchaseReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    protected $purchaseReturnRepository;

    public function __construct(PurchaseReturnRepository $purchaseReturnRepository)
    {
        $this->purchaseReturnRepository = $purchaseReturnRepository;
    }

    /**
     * Get all purchase returns for a company.
     */
    public function getAllPurchaseReturns(int $companyId, int $perPage = 15)
    {
        return $this->purchaseReturnRepository->getAllForCompany($companyId, $perPage);
    }

    /**
     * Get a purchase return by ID with its details.
     */
    public function getPurchaseReturnById(int $id)
    {
        return $this->purchaseReturnRepository->getByIdWithDetails($id);
    }

    /**
     * Create a new purchase return.
     */
    public function createPurchaseReturn(PurchaseReturnRequest $request)
    {
        $data = $this->preparePurchaseReturnData($request);
        
        // Validate items
        $this->validateItems($data['items']);
        
        // Create the purchase return with details
        $purchaseReturn = $this->purchaseReturnRepository->create($data);
        
        return $purchaseReturn;
    }

    /**
     * Update a purchase return.
     */
    public function updatePurchaseReturn(PurchaseReturnRequest $request, string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid purchase return ID');
        }
        
        $data = $this->preparePurchaseReturnData($request);
        
        // Validate items (pass purchase return ID to exclude current purchase return from IMEI/Serial check)
        $this->validateItems($data['items'], (int)$id);
        
        // Update the purchase return with details
        $purchaseReturn = $this->purchaseReturnRepository->update((int)$id, $data);
        
        return $purchaseReturn;
    }

    /**
     * Get a purchase return by encrypted ID with its details.
     */
    public function getPurchaseReturnByEncryptedId(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            return $this->purchaseReturnRepository->getByIdWithDetails((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete a purchase return.
     */
    public function deletePurchaseReturn(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid purchase return ID');
        }
        
        return $this->purchaseReturnRepository->delete((int)$id);
    }

    /**
     * Generate a reference number for a new purchase return.
     */
    public function generateReferenceNumber(int $companyId): string
    {
        return $this->purchaseReturnRepository->generateReferenceNumber($companyId);
    }

    /**
     * Prepare purchase return data from request.
     */
    protected function preparePurchaseReturnData(Request $request): array
    {
        $companyId = session('company.company_id');
        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');
        
        // Calculate totals
        $totalAmount = 0;
        $items = [];
        
        foreach ($request->items as $index => $itemId) {
            if (empty($itemId)) continue;
            
            $quantity = (float) $request->quantity[$index];
            $unitPrice = (float) $request->unit_price[$index];
            $total = $quantity * $unitPrice;

            $totalAmount += $total;
            
            // Get item type from request or fetch from database
            $itemType = null;
            if (isset($request->item_types[$index]) && !empty($request->item_types[$index])) {
                $itemType = $request->item_types[$index];
            } else {
                // Fetch item type from database if not provided
                $item = \Modules\Stock\Models\Item::find($itemId);
                $itemType = $item ? $item->type : null;
            }
            
            $itemData = [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'expiry_imei_serial' => null, // Will be set if IMEI/Serial is provided
                'expiry_imei_serial_in' => null, // Will be set if returned IMEI/Serial is provided
                'unit_price' => $unitPrice,
                'return_quantity_amount' => $quantity,
                'total' => $total,
                'return_note' => isset($request->item_note[$index]) ? $request->item_note[$index] : null,
                'return_status' => $request->status ?? 'draft',
                'user_id' => $userId,
                'outlet_id' => $outletId,
                'company_id' => $companyId,
                'del_status' => 'Live'
            ];
            
            // Handle IMEI/Serial numbers for specific product types
            // Now each row has a single IMEI/Serial value (not comma-separated)
            if (isset($request->imei_serial[$index]) && !empty($request->imei_serial[$index])) {
                $imeiSerialValue = trim($request->imei_serial[$index]);
                
                // For Medicine_Product, extract only the expiry date from "quantity - expiry_date" format
                if ($itemType === 'Medicine_Product' && strpos($imeiSerialValue, ' - ') !== false) {
                    // Split by " - " and take the expiry date part (second part)
                    $parts = explode(' - ', $imeiSerialValue, 2);
                    if (count($parts) === 2) {
                        $imeiSerialValue = trim($parts[1]); // Extract only the expiry date
                    }
                }
                
                $itemData['expiry_imei_serial'] = $imeiSerialValue;
            }
            
            // Handle returned IMEI/Serial/Medicine if status is "taken_by_sup_pro_returned"
            if (isset($request->returned_imei_serial[$index]) && !empty($request->returned_imei_serial[$index])) {
                if ($request->status === 'taken_by_sup_pro_returned' && 
                    in_array($itemType, ['IMEI_Product', 'Serial_Product', 'Medicine_Product'])) {
                    $returnedValue = trim($request->returned_imei_serial[$index]);
                    
                    // For Medicine_Product, extract only the expiry date from "quantity - expiry_date" format
                    if ($itemType === 'Medicine_Product' && strpos($returnedValue, ' - ') !== false) {
                        $parts = explode(' - ', $returnedValue, 2);
                        if (count($parts) === 2) {
                            $returnedValue = trim($parts[1]); // Extract only the expiry date
                        }
                    }
                    
                    // Store returned value in expiry_imei_serial_in field
                    $itemData['expiry_imei_serial_in'] = $returnedValue;
                }
            }
            
            $items[] = $itemData;
        }
        
        $purchaseReturnData = [
            'reference_no' => $request->reference_no ?? $this->generateReferenceNumber($companyId),
            'pur_ref_no' => $request->pur_ref_no ?? null,
            'supplier_id' => $request->supplier_id,
            'date' => $request->date,
            'purchase_date' => $request->purchase_date,
            'return_status' => $request->status ?? 'draft',
            'total_return_amount' => $totalAmount,
            'payment_method_id' => ($request->status === 'taken_by_sup_money_returned' && $request->payment_method_id) ? $request->payment_method_id : null,
            'payment_method_type' => ($request->status === 'taken_by_sup_money_returned' && $request->payment_method_type) ? $request->payment_method_type : null,
            'account_type' => ($request->status === 'taken_by_sup_money_returned' && $request->account_type) ? $request->account_type : null,
            'note' => $request->note,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live'
        ];
        
        return [
            'purchase_return' => $purchaseReturnData,
            'items' => $items
        ];
    }

    /**
     * Validate items based on business rules.
     * 
     * @param array $items The items to validate
     * @param int|null $excludePurchaseReturnId Optional purchase return ID to exclude from IMEI/Serial check (for updates)
     */
    protected function validateItems(array $items, ?int $excludePurchaseReturnId = null): void
    {
        if (empty($items)) {
            throw new \Exception('At least one item is required');
        }
        
        $itemIds = [];
        foreach ($items as $item) {
            $itemId = $item['item_id'];
            
            // Check if item can be added multiple times
            if (!$this->purchaseReturnRepository->canItemBeAddedMultipleTimes($itemId)) {
                if (in_array($itemId, $itemIds)) {
                    throw new \Exception('This item cannot be added multiple times');
                }
                $itemIds[] = $itemId;
            }
            
            // Check for duplicate IMEI/Serial numbers
            if (!empty($item['expiry_imei_serial'])) {
                // Check if this IMEI/Serial already exists in stock (excluding current purchase return if updating)
                $exists = $this->purchaseReturnRepository->checkImeiSerialExists($itemId, $item['expiry_imei_serial'], $excludePurchaseReturnId);
                if ($exists) {
                    throw new \Exception('IMEI/Serial number "' . $item['expiry_imei_serial'] . '" already exists in stock');
                }
            }
        }
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->purchaseReturnRepository->getDataTableData($params);
    }
}

