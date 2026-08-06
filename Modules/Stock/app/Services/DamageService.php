<?php

namespace Modules\Stock\Services;

use Modules\Stock\Repositories\DamageRepository;
use Modules\Stock\Models\Damage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Collection;

class DamageService
{
    protected $damageRepository;

    public function __construct(DamageRepository $damageRepository)
    {
        $this->damageRepository = $damageRepository;
    }

    /**
     * Get all damages for the current company
     */
    public function getAllDamages(): Collection
    {
        return $this->damageRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for damages listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->damageRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($damage, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $damage->id,
                'reference_no' => $damage->reference_no,
                'date' => formatDate($damage->date),
                'responsible_person_name' => $damage->employee->name ?? $damage->responsiblePerson->name ?? '',
                'responsible_person_email' => $damage->employee->email ?? $damage->responsiblePerson->email ?? '',
                'responsible_person_phone' => $damage->employee->phone ?? $damage->responsiblePerson->phone ?? '',
                'total_loss' => formatAmount($damage->total_loss),
                'encrypted_id' => $damage->encrypted_id,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['filteredCount'],
            'data' => $transformedData,
        ];
    }

    /**
     * Get damage by encrypted ID
     */
    public function getDamageByEncryptedId(string $encryptedId): ?Damage
    {
        return $this->damageRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new damage
     */
    public function createDamage(array $data): Damage
    {
        $this->validateDamageQuantitiesAgainstStock($data, null);
        $this->validateImeiSerialInOutlet($data);
        $preparedData = $this->prepareDamageData($data);
        return $this->damageRepository->create($preparedData);
    }

    /**
     * Update an existing damage
     */
    public function updateDamage(Damage $damage, array $data): bool
    {
        $this->validateDamageQuantitiesAgainstStock($data, $damage);
        $this->validateImeiSerialInOutlet($data);
        $preparedData = $this->prepareDamageData($data);
        return $this->damageRepository->update($damage, $preparedData);
    }

    /**
     * Validate that each IMEI/Serial in damage details exists in the current outlet's stock.
     */
    protected function validateImeiSerialInOutlet(array $data): void
    {
        $items = $data['items'] ?? [];
        $itemTypes = $data['item_types'] ?? [];
        $expiryImeiSerials = $data['expiry_imei_serial'] ?? [];
        $outletId = (int) session('outlet.outlet_id');

        $imeiSerialTypes = ['IMEI_Product', 'Serial_Product', 'Medicine_Product'];

        foreach ($items as $index => $itemId) {
            if (empty($itemId)) {
                continue;
            }
            $itemType = $itemTypes[$index] ?? null;
            if (!$itemType) {
                $item = \Modules\Stock\Models\Item::find($itemId);
                $itemType = $item ? $item->type : null;
            }
            if (!in_array($itemType, $imeiSerialTypes, true)) {
                continue;
            }

            $imeiSerialValue = isset($expiryImeiSerials[$index]) ? trim($expiryImeiSerials[$index]) : '';
            if ($imeiSerialValue === '') {
                continue;
            }

            // Medicine can be "qty - expiry" or multiple "qty - expiry, qty - expiry"
            $toCheck = [];
            if ($itemType === 'Medicine_Product' && strpos($imeiSerialValue, ' - ') !== false) {
                foreach (array_map('trim', explode(',', $imeiSerialValue)) as $entry) {
                    $parts = explode(' - ', $entry, 2);
                    if (count($parts) === 2) {
                        $toCheck[] = trim($parts[1]); // expiry part for stock match
                    }
                }
            } else {
                foreach (array_map('trim', explode(',', $imeiSerialValue)) as $v) {
                    if ($v !== '') {
                        $toCheck[] = $v;
                    }
                }
            }

            foreach ($toCheck as $oneValue) {
                $inQty = (float) DB::table('view_stock_detail')
                    ->where('item_id', $itemId)
                    ->where('outlet_id', $outletId)
                    ->where('type', 1)
                    ->where('expiry_imei_serial', $oneValue)
                    ->sum('stock_quantity');
                $outQty = (float) DB::table('view_stock_detail')
                    ->where('item_id', $itemId)
                    ->where('outlet_id', $outletId)
                    ->where('type', 2)
                    ->where('expiry_imei_serial', $oneValue)
                    ->sum('stock_quantity');
                $inStock = ($inQty - $outQty) > 0;

                if (!$inStock) {
                    $itemName = \Modules\Stock\Models\Item::find($itemId)->name ?? $itemId;
                    throw ValidationException::withMessages([
                        'expiry_imei_serial' => [
                            __(':value does not exist in this outlet\'s stock for item :item.', [
                                'value' => $oneValue,
                                'item' => $itemName,
                            ]),
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * Validate that damage quantity per item does not exceed current stock.
     * On update, available = current_stock + (existing damage qty for this item in this damage).
     */
    protected function validateDamageQuantitiesAgainstStock(array $data, ?Damage $existingDamage = null): void
    {
        $items = $data['items'] ?? [];
        $damageQuantities = $data['damage_quantity'] ?? [];
        $outletId = session('outlet.outlet_id');

        // Sum damage quantity per item_id from request (new quantities)
        $itemTotalQty = [];
        foreach ($items as $index => $itemId) {
            if (empty($itemId)) {
                continue;
            }
            $qty = (float) ($damageQuantities[$index] ?? 0);
            $itemTotalQty[$itemId] = ($itemTotalQty[$itemId] ?? 0) + $qty;
        }

        // On update: available = current_stock + (old damage qty for this item in this damage)
        $oldDamageQtyByItem = [];
        if ($existingDamage && $existingDamage->damageDetails) {
            foreach ($existingDamage->damageDetails as $detail) {
                $itemId = $detail->item_id;
                $oldDamageQtyByItem[$itemId] = ($oldDamageQtyByItem[$itemId] ?? 0) + (float) $detail->damage_quantity;
            }
        }

        foreach ($itemTotalQty as $itemId => $totalQty) {
            if ($totalQty <= 0) {
                continue;
            }
            $result = DB::table('items as p')
                ->select([
                    DB::raw("(
                        SELECT IFNULL(SUM(st3.stock_quantity), 0)
                        FROM view_stock_detail st3
                        WHERE p.id = st3.item_id
                            AND st3.type = 1
                            AND st3.outlet_id = " . (int) $outletId . "
                    ) as stock_qty"),
                    DB::raw("(
                        SELECT IFNULL(SUM(st4.stock_quantity), 0)
                        FROM view_stock_detail st4
                        WHERE p.id = st4.item_id
                            AND st4.type = 2
                            AND st4.outlet_id = " . (int) $outletId . "
                    ) as out_qty"),
                ])
                ->where('p.id', $itemId)
                ->where('p.company_id', $this->getCompanyId())
                ->where('p.del_status', 'Live')
                ->first();

            if (!$result) {
                continue;
            }
            $currentStock = (float) $result->stock_qty - (float) $result->out_qty;
            $currentStock = max(0, $currentStock);

            // When updating, we "add back" the old damage qty so we can allow re-saving
            $availableStock = $currentStock + ($oldDamageQtyByItem[$itemId] ?? 0);

            if ($totalQty > $availableStock) {
                $itemName = \Modules\Stock\Models\Item::find($itemId)->name ?? $itemId;
                throw ValidationException::withMessages([
                    'damage_quantity' => [
                        __('Damage quantity for :item cannot exceed current stock. Current stock: :stock, damage quantity: :qty.', [
                            'item' => $itemName,
                            'stock' => $availableStock,
                            'qty' => $totalQty,
                        ]),
                    ],
                ]);
            }
        }
    }

    /**
     * Delete a damage
     */
    public function deleteDamage(Damage $damage): bool
    {
        return $this->damageRepository->delete($damage);
    }

    /**
     * Generate reference number
     */
    public function generateReferenceNumber(): string
    {
        return $this->damageRepository->generateReferenceNumber();
    }

    /**
     * Prepare damage data for storage
     */
    protected function prepareDamageData(array $data): array
    {
        $companyId = $this->getCompanyId();
        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');

        // Calculate total loss from item totals
        $totalLoss = 0;
        $items = $data['items'] ?? [];
        $damageQuantities = $data['damage_quantity'] ?? [];
        $lastPurchasePrices = $data['last_purchase_price'] ?? [];
        $totalAmounts = $data['total_amount'] ?? [];
        $damageDate = $data['date'] ?? now()->toDateString();

        // Calculate total loss from item totals (using loss_amount if provided, otherwise use total_amount)
        $lossAmounts = $data['loss_amount'] ?? [];
        foreach ($totalAmounts as $index => $totalAmount) {
            $lossAmount = isset($lossAmounts[$index]) && $lossAmounts[$index] !== null && $lossAmounts[$index] !== '' 
                ? (float) $lossAmounts[$index] 
                : (float) $totalAmount;
            $totalLoss += $lossAmount;
        }

        // Prepare main damage data
        $damageData = [
            'date' => $damageDate,
            'reference_no' => $data['reference_no'] ?? $this->generateReferenceNumber(),
            'employee_id' => $data['employee_id'] ?? null,
            'total_loss' => $totalLoss,
            'note' => $data['note'] ?? null,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live',
        ];

        // Prepare damage details
        $details = [];
        $itemTypes = $data['item_types'] ?? [];
        $parentIds = $data['parent_ids'] ?? [];
        $expiryImeiSerials = $data['expiry_imei_serial'] ?? [];
        
        foreach ($items as $index => $itemId) {
            if (!empty($itemId)) {
                // Get item type from request or fetch from database
                $itemType = null;
                if (isset($itemTypes[$index]) && !empty($itemTypes[$index])) {
                    $itemType = $itemTypes[$index];
                } else {
                    // Fetch item type from database if not provided
                    $item = \Modules\Stock\Models\Item::find($itemId);
                    $itemType = $item ? $item->type : null;
                }
                
                $damageQuantity = (float) ($damageQuantities[$index] ?? 1);
                $lastPurchasePrice = (float) ($lastPurchasePrices[$index] ?? 0);
                $totalAmount = (float) ($totalAmounts[$index] ?? 0);
                $lossAmount = isset($lossAmounts[$index]) ? (float) $lossAmounts[$index] : $totalAmount;
                
                $detailData = [
                    'date' => $damageDate,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'expiry_imei_serial' => null,
                    'damage_quantity' => $damageQuantity,
                    'last_purchase_price' => $lastPurchasePrice,
                    'loss_amount' => $lossAmount,
                    'total_amount' => $totalAmount,
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ];
                
                // Handle IMEI/Serial/Medicine data
                if (isset($expiryImeiSerials[$index]) && !empty($expiryImeiSerials[$index])) {
                    $imeiSerialValue = trim($expiryImeiSerials[$index]);
                    
                    // For Medicine_Product, extract only the expiry date from "quantity - expiry_date" format
                    if ($itemType === 'Medicine_Product' && strpos($imeiSerialValue, ' - ') !== false) {
                        // Split by " - " and take the expiry date part (second part)
                        $parts = explode(' - ', $imeiSerialValue, 2);
                        if (count($parts) === 2) {
                            // For medicine, store as comma-separated expiry dates
                            $entries = explode(', ', $imeiSerialValue);
                            $expiryDates = [];
                            foreach ($entries as $entry) {
                                $entryParts = explode(' - ', $entry, 2);
                                if (count($entryParts) === 2) {
                                    $expiryDates[] = trim($entryParts[1]);
                                }
                            }
                            $imeiSerialValue = implode(', ', $expiryDates);
                        }
                    }
                    
                    $detailData['expiry_imei_serial'] = $imeiSerialValue;
                }
                
                $details[] = $detailData;
            }
        }

        return [
            'damage' => $damageData,
            'details' => $details,
        ];
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }
}

