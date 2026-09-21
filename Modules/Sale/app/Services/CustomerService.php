<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\CustomerRepository;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\CustomerReceive;
use Modules\Sale\Models\SaleReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    protected $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get all customers for the current company
     */
    public function getAllCustomers(): Collection
    {
        return $this->customerRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for customers listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw, ?string $typeFilter = null): array
    {
        // If type filter is applied, we need to get all customers first to calculate balance
        // Then filter and paginate based on balance type
        $companyId = $this->getCompanyId();
        $outletId = session('outlet.id');
        
        $baseQuery = $this->customerRepository->getBaseQuery($companyId);
        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        // order by id desc
        $query->orderBy('id', 'desc');

        // Get all matching customers to calculate balance
        $allCustomers = $query->get();
        
        // Calculate balance for each customer and filter by type if needed
        $customersWithBalance = $allCustomers->map(function ($customer) use ($outletId) {
            $balance = $this->getCustomerDue($customer->id, $outletId);
            $balanceType = $balance >= 0 ? 'Debit' : 'Credit';
            $balanceAmount = abs($balance);
            
            return [
                'customer' => $customer,
                'balance' => $balance,
                'balanceType' => $balanceType,
                'balanceAmount' => $balanceAmount,
            ];
        });

        // Apply type filter if specified
        if ($typeFilter && in_array($typeFilter, ['Debit', 'Credit'])) {
            $customersWithBalance = $customersWithBalance->filter(function ($item) use ($typeFilter) {
                return $item['balanceType'] === $typeFilter;
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $customersWithBalance->count();

        // Apply pagination
        $paginatedCustomers = $customersWithBalance->slice($start, $length);

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        // Transform data for DataTable
        $transformedData = $paginatedCustomers->map(function ($item, $index) use ($startingNumber) {
            $customer = $item['customer'];
            
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'current_balance' => formatAmount($item['balanceAmount']) . ' (' . $item['balanceType'] . ')',
                'balance_amount' => $item['balanceAmount'],
                'balance_type' => $item['balanceType'],
                'encrypted_id' => $customer->encrypted_id,
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
     * Calculate customer current balance based on CodeIgniter logic
     * 
     * @param int $customerId
     * @param int|null $outletId
     * @return float
     */
    public function getCustomerDue(int $customerId, ?int $outletId = null): float
    {
        $companyId = $this->getCompanyId();
        
        // Get customer
        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$customer) {
            return 0;
        }

        // If customer is "Walk-in Customer", return 0
        if ($customer->name === 'Walk-in Customer') {
            return 0;
        }

        // Get outlet ID from session if not provided
        if (!$outletId) {
            $outletId = session('outlet.id');
        }

        // Calculate total sale due (from sales)
        // Note: CodeIgniter version filters by delivery_status = 'Cash Received'
        // In Laravel, we sum all due_amount for the customer
        $totalSaleDue = Sale::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        // Check if delivery_status column exists and filter if it does
        if (DB::getSchemaBuilder()->hasColumn('sales', 'delivery_status')) {
            $totalSaleDue->where('delivery_status', 'Cash Received');
        }
        
        if ($outletId) {
            $totalSaleDue->where('outlet_id', $outletId);
        }
        
        $totalSaleDueAmount = $totalSaleDue->sum('due_amount') ?? 0;

        // Calculate total due received
        $totalDueReceived = CustomerReceive::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $totalDueReceived->where('outlet_id', $outletId);
        }
        
        $totalDueReceivedAmount = $totalDueReceived->sum('amount') ?? 0;

        // Calculate total sale return
        $totalSaleReturn = SaleReturn::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $totalSaleReturn->where('outlet_id', $outletId);
        }
        
        $totalSaleReturnAmount = $totalSaleReturn->sum('due') ?? 0;

        // Calculate opening balance based on type
        $openingBalance = floatval($customer->opening_balance ?? 0);
        $openingBalanceType = $customer->opening_balance_type ?? 'Debit';

        if (in_array($openingBalanceType, ['Credit', 'Cr'])) {
            $balance = -$openingBalance - $totalDueReceivedAmount + $totalSaleDueAmount - $totalSaleReturnAmount;
        } else {
            $balance = $openingBalance - $totalDueReceivedAmount + $totalSaleDueAmount - $totalSaleReturnAmount;
        }

        return $balance;
    }

    /**
     * Get customer by encrypted ID
     */
    public function getCustomerByEncryptedId(string $encryptedId): ?Customer
    {
        return $this->customerRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(string $id): ?Customer
    {
        return $this->customerRepository->findById($id);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        // Check if trying to create "Walk-in Customer" and one already exists
        if (isset($data['name']) && $data['name'] === 'Walk-in Customer') {
            $existingWalkIn = Customer::where('name', 'Walk-in Customer')
                ->where('company_id', $this->getCompanyId())
                ->where('del_status', 'Live')
                ->first();
            
            if ($existingWalkIn) {
                throw new \Exception('A "Walk-in Customer" already exists for this company. Only one "Walk-in Customer" is allowed per company.');
            }
        }

        // Phone duplicate check — prevent adding customer with same phone number
        if (!empty($data['phone'])) {
            $phone = trim($data['phone']);
            $existingCustomer = Customer::where('company_id', $this->getCompanyId())
                ->where('del_status', 'Live')
                ->whereRaw('LOWER(TRIM(phone)) = LOWER(?)', [$phone])
                ->first();
            
            if ($existingCustomer) {
                throw new \Exception('A customer with this phone number already exists. Please use the existing customer or update it.');
            }
        }
        
        $storeData = $this->prepareCustomerData($data);
        return $this->customerRepository->create($storeData);
    }

    /**
     * Update an existing customer
     */
    public function updateCustomer(Customer $customer, array $data): bool
    {
        // Prevent changing name if customer is "Walk-in Customer"
        if ($customer->name === 'Walk-in Customer' && isset($data['name']) && $data['name'] !== 'Walk-in Customer') {
            throw new \Exception('The name "Walk-in Customer" cannot be changed.');
        }
        
        $updateData = $this->prepareCustomerData($data, true, $customer);
        // Bump the logical clock so a web edit wins the LWW compare against an
        // older desktop edit (and vice-versa: the desktop edit bumps its own
        // SyncVersion on the next push).
        $updateData['sync_version'] = ((int) ($customer->sync_version ?? 0)) + 1;
        return $this->customerRepository->update($customer, $updateData);
    }

    /**
     * Delete a customer
     */
    public function deleteCustomer(Customer $customer): bool
    {
        // Prevent deletion of "Walk-in Customer"
        if ($customer->name === 'Walk-in Customer') {
            throw new \Exception('"Walk-in Customer" cannot be deleted.');
        }
        
        return $this->customerRepository->delete($customer);
    }

    /**
     * Get active customers for dropdown
     */
    public function getActiveCustomers(): Collection
    {
        return $this->customerRepository->getActiveCustomers($this->getCompanyId());
    }

    /**
     * Search customers by name
     */
    public function searchCustomers(string $name): Collection
    {
        return $this->customerRepository->searchByName($this->getCompanyId(), $name);
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(): array
    {
        $companyId = $this->getCompanyId();
        return [
            'total' => $this->customerRepository->getTotalCount($companyId),
            'with_balance' => $this->customerRepository->getWithOutstandingBalance($companyId)->count(),
        ];
    }

    /**
     * Prepare customer data for store/update
     */
    protected function prepareCustomerData(array $data, bool $isUpdate = false, ?Customer $customer = null): array
    {
        $preparedData = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'discount' => isset($data['discount']) && $data['discount'] !== '' ? $data['discount'] : null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'date_of_anniversary' => $data['date_of_anniversary'] ?? null,
            'gst_number' => ! empty($data['gst_number'])
                ? \App\Helpers\GstinValidator::normalize($data['gst_number'])
                : null,
            'customer_type' => isset($data['customer_type']) && $data['customer_type'] !== '' ? $data['customer_type'] : null,
            'same_or_diff_state' => $data['same_or_diff_state'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'business_type' => $data['business_type'] ?? 'B2C',
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
        ];

        if ($isUpdate && $customer) {
            $preparedData['opening_balance'] = !empty($data['opening_balance']) 
                ? $data['opening_balance'] 
                : $customer->opening_balance;
            $preparedData['opening_balance_type'] = !empty($data['opening_balance_type']) 
                ? $data['opening_balance_type'] 
                : $customer->opening_balance_type;
            $preparedData['updated_at'] = now();
        } else {
            $preparedData['opening_balance'] = !empty($data['opening_balance']) 
                ? $data['opening_balance'] 
                : 0;
            $preparedData['opening_balance_type'] = !empty($data['opening_balance_type']) 
                ? $data['opening_balance_type'] 
                : 'Debit';
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
}

