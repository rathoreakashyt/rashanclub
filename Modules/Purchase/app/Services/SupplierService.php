<?php

namespace Modules\Purchase\Services;

use Modules\Purchase\Repositories\SupplierRepository;
use Modules\Purchase\Models\Supplier;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\SupplierPayment;
use Modules\Purchase\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    protected $supplierRepository;

    public function __construct(SupplierRepository $supplierRepository)
    {
        $this->supplierRepository = $supplierRepository;
    }

    /**
     * Get all suppliers for the current company
     */
    public function getAllSuppliers(): Collection
    {
        return $this->supplierRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for suppliers listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw, ?string $typeFilter = null): array
    {
        // If type filter is applied, we need to get all suppliers first to calculate balance
        // Then filter and paginate based on balance type
        $companyId = $this->getCompanyId();
        $outletId = session('outlet.id');
        
        $baseQuery = $this->supplierRepository->getBaseQuery($companyId);
        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        // Order by id desc
        $query->orderBy('id', 'desc');

        // Get all matching suppliers to calculate balance
        $allSuppliers = $query->get();
        
        // Calculate balance for each supplier and filter by type if needed
        $suppliersWithBalance = $allSuppliers->map(function ($supplier) use ($outletId) {
            $balance = $this->getSupplierDue($supplier->id, $outletId);
            $balanceType = $balance >= 0 ? 'Debit' : 'Credit';
            $balanceAmount = abs($balance);
            
            return [
                'supplier' => $supplier,
                'balance' => $balance,
                'balanceType' => $balanceType,
                'balanceAmount' => $balanceAmount,
            ];
        });

        // Apply type filter if specified
        if ($typeFilter && in_array($typeFilter, ['Debit', 'Credit'])) {
            $suppliersWithBalance = $suppliersWithBalance->filter(function ($item) use ($typeFilter) {
                return $item['balanceType'] === $typeFilter;
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $suppliersWithBalance->count();

        // Apply pagination
        $paginatedSuppliers = $suppliersWithBalance->slice($start, $length);

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        // Transform data for DataTable
        $transformedData = $paginatedSuppliers->map(function ($item, $index) use ($startingNumber) {
            $supplier = $item['supplier'];
            
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $supplier->id,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone' => $supplier->phone,
                'current_balance' => formatAmount($item['balanceAmount']) . ' (' . $item['balanceType'] . ')',
                'balance_amount' => $item['balanceAmount'],
                'balance_type' => $item['balanceType'],
                'encrypted_id' => $supplier->encrypted_id,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData->values(),
        ];
    }

    /**
     * Get supplier by encrypted ID
     */
    public function getSupplierByEncryptedId(string $encryptedId): ?Supplier
    {
        return $this->supplierRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new supplier
     */
    public function createSupplier(array $data): Supplier
    {
        $storeData = $this->prepareSupplierData($data);
        return $this->supplierRepository->create($storeData);
    }

    /**
     * Update an existing supplier
     */
    public function updateSupplier(Supplier $supplier, array $data): bool
    {
        $updateData = $this->prepareSupplierData($data, true);
        return $this->supplierRepository->update($supplier, $updateData);
    }

    /**
     * Delete a supplier
     */
    public function deleteSupplier(Supplier $supplier): bool
    {
        return $this->supplierRepository->delete($supplier);
    }

    /**
     * Get active suppliers for dropdown
     */
    public function getActiveSuppliers(): Collection
    {
        return $this->supplierRepository->getActiveSuppliers($this->getCompanyId());
    }

    /**
     * Search suppliers by name
     */
    public function searchSuppliers(string $name): Collection
    {
        return $this->supplierRepository->searchByName($this->getCompanyId(), $name);
    }

    /**
     * Get supplier statistics
     */
    public function getSupplierStatistics(): array
    {
        $companyId = $this->getCompanyId();
        return [
            'total' => $this->supplierRepository->getTotalCount($companyId),
            'with_balance' => $this->supplierRepository->getWithOutstandingBalance($companyId)->count(),
        ];
    }

    /**
     * Prepare supplier data for store/update
     */
    protected function prepareSupplierData(array $data, bool $isUpdate = false): array
    {
        $preparedData = [
            'name' => $data['name'],
            'contact_person' => $data['contact_person'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
            'opening_balance' => isset($data['opening_balance']) && !is_null($data['opening_balance']) 
                ? $data['opening_balance'] 
                : 0,
            'opening_balance_type' => !empty($data['opening_balance_type']) 
                ? $data['opening_balance_type'] 
                : 'Debit',
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
        ];

        if ($isUpdate) {
            $preparedData['updated_at'] = now();
        } else {
            $preparedData['del_status'] = 'Live';
        }

        return $preparedData;
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }

    /**
     * Calculate supplier current balance based on CodeIgniter logic
     * 
     * @param int $supplierId
     * @param int|null $outletId
     * @return float
     */
    public function getSupplierDue(int $supplierId, ?int $outletId = null): float
    {
        $companyId = $this->getCompanyId();
        
        // Get supplier
        $supplier = Supplier::where('id', $supplierId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$supplier) {
            return 0;
        }

        // Get outlet ID from session if not provided
        if (!$outletId) {
            $outletId = session('outlet.id');
        }

        // Calculate total supplier due (from purchases)
        $supplierDueQuery = Purchase::where('supplier_id', $supplierId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $supplierDueQuery->where('outlet_id', $outletId);
        }
        
        $supplierDueAmount = $supplierDueQuery->sum('due_amount') ?? 0;

        // Calculate total supplier payment
        $supplierPaymentQuery = SupplierPayment::where('supplier_id', $supplierId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $supplierPaymentQuery->where('outlet_id', $outletId);
        }
        
        $supplierPaymentAmount = $supplierPaymentQuery->sum('amount') ?? 0;

        // Calculate total purchase return
        $purchaseReturnQuery = PurchaseReturn::where('supplier_id', $supplierId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $purchaseReturnQuery->where('outlet_id', $outletId);
        }
        
        $purchaseReturnAmount = $purchaseReturnQuery->sum('total_return_amount') ?? 0;

        // Calculate opening balance based on type
        $openingBalance = floatval($supplier->opening_balance ?? 0);
        $openingBalanceType = $supplier->opening_balance_type ?? 'Debit';

        // Formula from CodeIgniter:
        // If Credit: (supplier_due - supplier_payment) + opening_balance - purchase_return
        // If Debit: (supplier_due - supplier_payment) - opening_balance - purchase_return
        if ($openingBalanceType == 'Credit') {
            $remainingDue = ($supplierDueAmount - $supplierPaymentAmount) + $openingBalance - $purchaseReturnAmount;
        } else {
            $remainingDue = ($supplierDueAmount - $supplierPaymentAmount) - $openingBalance - $purchaseReturnAmount;
        }

        return $remainingDue;
    }
}

