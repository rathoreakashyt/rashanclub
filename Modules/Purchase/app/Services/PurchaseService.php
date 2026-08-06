<?php

namespace Modules\Purchase\Services;

use Modules\Purchase\Repositories\PurchaseRepository;
use Modules\Purchase\Http\Requests\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    protected $purchaseRepository;

    public function __construct(PurchaseRepository $purchaseRepository)
    {
        $this->purchaseRepository = $purchaseRepository;
    }

    /**
     * Get all purchases for a company.
     */
    public function getAllPurchases(int $companyId, int $perPage = 15)
    {
        return $this->purchaseRepository->getAllForCompany($companyId, $perPage);
    }

    /**
     * Get a purchase by ID with its details.
     */
    public function getPurchaseById(int $id)
    {
        return $this->purchaseRepository->getByIdWithDetails($id);
    }

    /**
     * Get a purchase by encrypted ID with its details.
     */
    public function getPurchaseByEncryptedId(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            return $this->purchaseRepository->getByIdWithDetails((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new purchase.
     */
    public function createPurchase(PurchaseRequest $request)
    {
        $data = $this->preparePurchaseData($request);
        
        // Validate items
        $this->validateItems($data['items']);
        
        // Create the purchase with details
        $purchase = $this->purchaseRepository->create($data);
        
        // Add payments if provided
        if (!empty($data['payments'])) {
            $this->purchaseRepository->createPurchasePayments($purchase->id, $data['payments']);
            $this->purchaseRepository->updatePurchasePaidAmount($purchase->id);
        }
        
        return $purchase;
    }

    /**
     * Update a purchase.
     */
    public function updatePurchase(PurchaseRequest $request, string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid purchase ID');
        }
        
        $data = $this->preparePurchaseData($request);
        
        // Validate items (pass purchase ID to exclude current purchase from IMEI/Serial check)
        $this->validateItems($data['items'], (int)$id);
        
        // Update the purchase with details
        $purchase = $this->purchaseRepository->update((int)$id, $data);
        
        // Handle payments: always hard delete existing payments, then create new ones if provided
        \Modules\Purchase\Models\PurchasePayment::where('purchase_id', (int)$id)->delete();
        
        if (!empty($data['payments'])) {
            $this->purchaseRepository->createPurchasePayments($purchase->id, $data['payments']);
            $this->purchaseRepository->updatePurchasePaidAmount($purchase->id);
        } else {
            $this->purchaseRepository->updatePurchasePaidAmount($purchase->id);
        }
        
        return $purchase;
    }

    /**
     * Delete a purchase.
     */
    public function deletePurchase(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid purchase ID');
        }
        
        return $this->purchaseRepository->delete((int)$id);
    }

    /**
     * Generate a reference number for a new purchase.
     */
    public function generateReferenceNumber(int $companyId): string
    {
        return $this->purchaseRepository->generateReferenceNumber($companyId);
    }

    /**
     * Prepare purchase data from request.
     */
    protected function preparePurchaseData(Request $request): array
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
                'unit_price' => $unitPrice,
                'quantity_amount' => $quantity,
                'total' => $total,
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
            
            $items[] = $itemData;
        }
        
        // Calculate discount
        $discountValue = $request->discount ?? '';
        $discountAmount = 0;
        
        // Save the original discount value (with % if provided) for display
        // Convert to string to preserve the % symbol
        $discountToSave = $discountValue ? trim((string) $discountValue) : '0';
        
        if ($discountValue) {
            $discountValueStr = trim((string) $discountValue);
            if ($discountValueStr !== '') {
                if (strpos($discountValueStr, '%') !== false) {
                    // Percentage discount - calculate the amount
                    $discountPercent = (float) str_replace('%', '', $discountValueStr);
                    $discountAmount = $totalAmount * ($discountPercent / 100);
                } else {
                    // Fixed amount discount
                    $discountAmount = (float) $discountValueStr;
                }
            }
        }
        
        // Calculate grand total after discount
        $grandTotal = $totalAmount - $discountAmount;
        
        // Calculate total paid amount from payments
        $totalPaid = 0;
        $payments = [];
        
        if ($request->has('payments') && !empty($request->payments)) {
            foreach ($request->payments as $payment) {
                if (!empty($payment['payment_id']) && !empty($payment['amount'])) {
                    $totalPaid += (float) $payment['amount'];
                    $payments[] = [
                        'payment_id' => $payment['payment_id'],
                        'amount' => $payment['amount'],
                        'note' => $payment['note'] ?? null
                    ];
                }
            }
        }
        
        $purchaseData = [
            'reference_no' => $request->reference_no ?? $this->generateReferenceNumber($companyId),
            'invoice_no' => $request->invoice_no,
            'supplier_id' => $request->supplier_id,
            'date' => $request->date,
            'other' => 0, // Default value
            'grand_total' => $grandTotal,
            'paid' => $totalPaid,
            'due_amount' => $grandTotal - $totalPaid,
            'note' => $request->note,
            'discount' => $discountToSave, // Save original value (with % if provided)
            'attachment' => null, // Will be handled separately if needed
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live'
        ];
        
        return [
            'purchase' => $purchaseData,
            'items' => $items,
            'payments' => $payments
        ];
    }

    /**
     * Validate items based on business rules.
     * 
     * @param array $items The items to validate
     * @param int|null $excludePurchaseId Optional purchase ID to exclude from IMEI/Serial check (for updates)
     */
    protected function validateItems(array $items, ?int $excludePurchaseId = null): void
    {
        if (empty($items)) {
            throw new \Exception('At least one item is required');
        }
        
        $itemIds = [];
        foreach ($items as $item) {
            $itemId = $item['item_id'];
            
            // Check if item can be added multiple times
            if (!$this->purchaseRepository->canItemBeAddedMultipleTimes($itemId)) {
                if (in_array($itemId, $itemIds)) {
                    throw new \Exception('This item cannot be added multiple times');
                }
                $itemIds[] = $itemId;
            }
            
            // Check for duplicate IMEI/Serial numbers
            if (!empty($item['expiry_imei_serial'])) {
                // Check if this IMEI/Serial already exists in stock (excluding current purchase if updating)
                $exists = $this->purchaseRepository->checkImeiSerialExists($itemId, $item['expiry_imei_serial'], $excludePurchaseId);
                if ($exists) {
                    throw new \Exception('IMEI/Serial number "' . $item['expiry_imei_serial'] . '" already exists in stock');
                }
            }
        }
    }

    /**
     * Add payment to a purchase.
     */
    public function addPayment(int $purchaseId, array $paymentData): void
    {
        // Validate payment data
        $this->validatePaymentData($paymentData);
        
        // Create the payment
        $this->purchaseRepository->createPurchasePayments($purchaseId, [$paymentData]);
        
        // Update purchase paid amount
        $this->purchaseRepository->updatePurchasePaidAmount($purchaseId);
    }

    /**
     * Add multiple payments to a purchase.
     */
    public function addMultiplePayments(int $purchaseId, array $payments): void
    {
        // Validate all payment data
        foreach ($payments as $payment) {
            $this->validatePaymentData($payment);
        }
        
        // Create the payments
        $this->purchaseRepository->createPurchasePayments($purchaseId, $payments);
        
        // Update purchase paid amount
        $this->purchaseRepository->updatePurchasePaidAmount($purchaseId);
    }

    /**
     * Get all payments for a purchase.
     */
    public function getPurchasePayments(int $purchaseId)
    {
        return $this->purchaseRepository->getPurchasePayments($purchaseId);
    }

    /**
     * Delete a purchase payment.
     */
    public function deletePurchasePayment(int $paymentId): bool
    {
        return $this->purchaseRepository->deletePurchasePayment($paymentId);
    }

    /**
     * Validate payment data.
     */
    protected function validatePaymentData(array $paymentData): void
    {
        if (empty($paymentData['payment_id'])) {
            throw new \Exception('Payment method is required');
        }
        
        if (empty($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new \Exception('Payment amount must be greater than 0');
        }
        
        // Check if payment method exists
        $paymentMethod = \Modules\Accounting\Models\PaymentMethod::find($paymentData['payment_id']);
        if (!$paymentMethod) {
            throw new \Exception('Invalid payment method');
        }
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->purchaseRepository->getDataTableData($params);
    }
}
