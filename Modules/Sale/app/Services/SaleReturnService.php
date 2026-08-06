<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\SaleReturnRepository;
use Modules\Sale\Http\Requests\SaleReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleReturnService
{
    protected $saleReturnRepository;

    public function __construct(SaleReturnRepository $saleReturnRepository)
    {
        $this->saleReturnRepository = $saleReturnRepository;
    }

    /**
     * Get all sale returns for a company.
     */
    public function getAllSaleReturns(int $companyId, int $perPage = 15)
    {
        return $this->saleReturnRepository->getAllForCompany($companyId, $perPage);
    }

    /**
     * Get a sale return by ID with its details.
     */
    public function getSaleReturnById(int $id)
    {
        return $this->saleReturnRepository->getByIdWithDetails($id);
    }

    /**
     * Get a sale return by encrypted ID with its details.
     */
    public function getSaleReturnByEncryptedId(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            return $this->saleReturnRepository->getByIdWithDetails((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new sale return.
     */
    public function createSaleReturn(SaleReturnRequest $request)
    {
        $data = $this->prepareSaleReturnData($request);
        
        // Validate items and return quantities
        $this->validateItems($data['items'], $request->sale_id);
        
        // Create the sale return with details
        $saleReturn = $this->saleReturnRepository->create($data);
        
        return $saleReturn;
    }

    /**
     * Update a sale return.
     */
    public function updateSaleReturn(SaleReturnRequest $request, string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid sale return ID');
        }
        
        $data = $this->prepareSaleReturnData($request);
        
        // Validate items and return quantities (pass sale return ID to exclude from quantity check)
        $this->validateItems($data['items'], $request->sale_id, (int)$id);
        
        // Update the sale return with details
        $saleReturn = $this->saleReturnRepository->update((int)$id, $data);
        
        return $saleReturn;
    }

    /**
     * Delete a sale return.
     */
    public function deleteSaleReturn(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid sale return ID');
        }
        
        return $this->saleReturnRepository->delete((int)$id);
    }

    /**
     * Generate a reference number for a new sale return.
     */
    public function generateReferenceNumber(int $companyId): string
    {
        return $this->saleReturnRepository->generateReferenceNumber($companyId);
    }

    /**
     * Prepare sale return data from request.
     */
    protected function prepareSaleReturnData(Request $request): array
    {
        $companyId = session('company.company_id');
        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');
        
        // Calculate totals
        $totalReturnAmount = 0;
        $items = [];
        
        foreach ($request->items as $index => $itemId) {
            if (empty($itemId)) continue;
            
            $saleQuantity = (float) $request->sale_quantities[$index];
            $returnQuantity = (float) $request->return_quantities[$index];
            $unitPriceSale = (float) $request->unit_prices_sale[$index];
            $unitPriceReturn = (float) $request->unit_prices_return[$index];
            $total = $returnQuantity * $unitPriceReturn;

            $totalReturnAmount += $total;
            
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
                'sale_id' => $request->sale_id,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'sale_quantity_amount' => $saleQuantity,
                'return_quantity_amount' => $returnQuantity,
                'unit_price_in_sale' => $unitPriceSale,
                'unit_price_in_return' => $unitPriceReturn,
                'expiry_imei_serial' => null, // Will be set if IMEI/Serial is provided
                'user_id' => $userId,
                'outlet_id' => $outletId,
                'company_id' => $companyId,
                'del_status' => 'Live'
            ];
            
            // Handle IMEI/Serial numbers for specific product types
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
            
            $items[] = $itemData;
        }
        
        // Calculate paid and due amounts
        $paidAmount = (float) $request->paid;
        $dueAmount = $totalReturnAmount - $paidAmount;
        
        $saleReturnData = [
            'reference_no' => $request->reference_no ?? $this->generateReferenceNumber($companyId),
            'date' => $request->date,
            'customer_id' => $request->customer_id,
            'sale_id' => $request->sale_id,
            'total_return_amount' => $totalReturnAmount,
            'paid' => $paidAmount,
            'due' => $dueAmount,
            'payment_method_id' => $request->payment_method_id,
            'note' => $request->note,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live'
        ];
        
        return [
            'sale_return' => $saleReturnData,
            'items' => $items
        ];
    }

    /**
     * Validate items based on business rules.
     * 
     * @param array $items The items to validate
     * @param int $saleId The sale ID
     * @param int|null $excludeSaleReturnId Optional sale return ID to exclude from quantity check (for updates)
     */
    protected function validateItems(array $items, int $saleId, ?int $excludeSaleReturnId = null): void
    {
        if (empty($items)) {
            throw new \Exception('At least one item is required');
        }
        
        foreach ($items as $item) {
            $itemId = $item['item_id'];
            $returnQuantity = (float) $item['return_quantity_amount'];
            
            // Get total sold quantity for this item from this sale
            $soldQuantity = $this->saleReturnRepository->getTotalSoldQuantity($saleId, $itemId);
            
            if ($soldQuantity <= 0) {
                throw new \Exception('Item not found in the selected sale invoice');
            }
            
            // Get total already returned quantity for this item from this sale (excluding current return if updating)
            $returnedQuantity = $this->saleReturnRepository->getTotalReturnedQuantity($saleId, $itemId, $excludeSaleReturnId);
            
            // Calculate available return quantity
            $availableReturnQuantity = $soldQuantity - $returnedQuantity;
            
            // Check if return quantity exceeds available quantity
            if ($returnQuantity > $availableReturnQuantity) {
                $itemName = \Modules\Stock\Models\Item::find($itemId)->name ?? 'Item';
                throw new \Exception("Return quantity for {$itemName} cannot exceed available quantity. Available: {$availableReturnQuantity}, Requested: {$returnQuantity}");
            }
            
            // Check if return quantity is greater than sold quantity
            if ($returnQuantity > $soldQuantity) {
                $itemName = \Modules\Stock\Models\Item::find($itemId)->name ?? 'Item';
                throw new \Exception("Return quantity for {$itemName} cannot exceed sold quantity ({$soldQuantity})");
            }
        }
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->saleReturnRepository->getDataTableData($params);
    }

    /**
     * Get customer sale invoices (AJAX endpoint)
     */
    public function getCustomerSaleInvoices(int $customerId): array
    {
        $companyId = session('company.company_id');
        
        $sales = \Modules\Sale\Models\Sale::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'encrypted_id' => $sale->encrypted_id,
                    'sale_no' => $sale->sale_no,
                    'date' => formatDate($sale->sale_date),
                    'total_payable' => formatAmount($sale->total_payable ?? 0),
                ];
            });
        
        return $sales->toArray();
    }

    /**
     * Get sale invoice items (AJAX endpoint)
     */
    public function getSaleInvoiceItems(int $saleId): array
    {
        $sale = \Modules\Sale\Models\Sale::with([
            'saleDetails' => function($query) {
                $query->where('del_status', 'Live');
            },
            'saleDetails.item.parent'
        ])->find($saleId);
        
        if (!$sale) {
            return [];
        }
        
        $items = [];
        foreach ($sale->saleDetails as $detail) {
            if (!$detail->item) continue;
            
            // Get already returned quantity for this item from this sale
            $returnedQuantity = $this->saleReturnRepository->getTotalReturnedQuantity($saleId, $detail->item_id);
            $availableReturnQuantity = max(0, (float)$detail->qty - $returnedQuantity);
            
            $itemName = $detail->item->name;
            if ($detail->item->parent_id && $detail->item->parent) {
                $itemName = $detail->item->parent->name . ' - ' . $detail->item->name;
            }
            
            $items[] = [
                'id' => $detail->item_id,
                'name' => $itemName,
                'code' => $detail->item->code ?? '',
                'brand' => $detail->item->parent ? $detail->item->parent->name : '',
                'sale_quantity' => (float) $detail->qty,
                'returned_quantity' => $returnedQuantity,
                'available_return_quantity' => $availableReturnQuantity,
                'unit_price' => (float) $detail->menu_unit_price,
                'item_type' => $detail->item->type ?? 'General_Product',
                'expiry_imei_serial' => $detail->expiry_imei_serial ?? '',
            ];
        }
        
        return $items;
    }
}
