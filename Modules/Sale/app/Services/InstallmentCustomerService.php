<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\CustomerRepository;
use Modules\Sale\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class InstallmentCustomerService
{
    protected $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get all installment customers for the current company
     */
    public function getAllInstallmentCustomers(): Collection
    {
        return $this->customerRepository->all($this->getCompanyId());
    }

    /**
     * Get active installment customers for dropdown/selection
     */
    public function getActiveInstallmentCustomers(): Collection
    {
        return Customer::where([
            ['del_status', 'Live'],
            ['company_id', $this->getCompanyId()],
        ])
        ->select('id', 'name', 'phone', 'email')
        ->orderBy('name')
        ->get();
    }

    /**
     * Get DataTable data for installment customers listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->customerRepository->getInstallmentDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($customer, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'permanent_address' => $customer->permanent_address,
                'work_address' => $customer->work_address,
                'g_name' => $customer->g_name,
                'g_mobile' => $customer->g_mobile,
                'opening_balance' => formatAmount($customer->opening_balance),
                'encrypted_id' => $customer->encrypted_id,
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
     * Get installment customer by encrypted ID
     */
    public function getInstallmentCustomerByEncryptedId(string $encryptedId): ?Customer
    {
        return $this->customerRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new installment customer
     */
    public function createInstallmentCustomer(array $data, Request $request): Customer
    {
        $storeData = $this->prepareInstallmentCustomerData($data, $request);
        return $this->customerRepository->create($storeData);
    }

    /**
     * Update an existing installment customer
     */
    public function updateInstallmentCustomer(Customer $customer, array $data, Request $request): bool
    {
        $updateData = $this->prepareInstallmentCustomerData($data, $request, true, $customer);
        return $this->customerRepository->update($customer, $updateData);
    }

    /**
     * Delete an installment customer
     */
    public function deleteInstallmentCustomer(Customer $customer): bool
    {
        return $this->customerRepository->delete($customer);
    }

    /**
     * Get installment customer statistics
     */
    public function getInstallmentCustomerStatistics(): array
    {
        $companyId = $this->getCompanyId();
        return [
            'total' => $this->customerRepository->getTotalCount($companyId),
            'with_balance' => $this->customerRepository->getWithOutstandingBalance($companyId)->count(),
        ];
    }

    /**
     * Prepare installment customer data for store/update
     */
    protected function prepareInstallmentCustomerData(array $data, Request $request, bool $isUpdate = false, ?Customer $customer = null): array
    {
        $preparedData = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => !empty($data['address']) ? truncateText($data['address'], 30, 10) : ($customer ? $customer->address : null),
            'permanent_address' => !empty($data['permanent_address']) ? truncateText($data['permanent_address'], 30, 10) : ($customer ? $customer->permanent_address : null),
            'work_address' => !empty($data['work_address']) ? truncateText($data['work_address'], 30, 10) : ($customer ? $customer->work_address : null),
            'g_name' => $data['g_name'] ?? ($customer ? $customer->g_name : null),
            'g_mobile' => $data['g_mobile'] ?? ($customer ? $customer->g_mobile : null),
            'g_pre_address' => !empty($data['g_pre_address']) ? truncateText($data['g_pre_address'], 30, 10) : ($customer ? $customer->g_pre_address : null),
            'g_work_address' => !empty($data['g_work_address']) ? truncateText($data['g_work_address'], 30, 10) : ($customer ? $customer->g_work_address : null),
            'credit_limit' => $data['credit_limit'] ?? 0,
            'discount' => $data['discount'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'date_of_anniversary' => $data['date_of_anniversary'] ?? null,
            'customer_type' => $data['customer_type'] ?? null,
            'is_installment_customer' => 'Yes',
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
        ];

        // Handle file uploads - required on create, optional on update
        $preparedData['customer_nid'] = $this->handleFileUpload($request, 'customer_nid', 'customers', $customer ? $customer->customer_nid : null);
        $preparedData['photo'] = $this->handleFileUpload($request, 'photo', 'customers', $customer ? $customer->photo : null);
        $preparedData['g_nid'] = $this->handleFileUpload($request, 'g_nid', 'customers', $customer ? $customer->g_nid : null);
        $preparedData['g_photo'] = $this->handleFileUpload($request, 'g_photo', 'customers', $customer ? $customer->g_photo : null);

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
     * Handle file upload
     */
    protected function handleFileUpload(Request $request, string $fieldName, string $folder, ?string $oldFile = null): ?string
    {
        if ($request->hasFile($fieldName)) {
            // Delete old file if exists
            if ($oldFile && file_exists(public_path($oldFile))) {
                unlink(public_path($oldFile));
            }

            $file = $request->file($fieldName);
            $fileName = time() . '_' . $fieldName . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/' . $folder), $fileName);
            return 'uploads/' . $folder . '/' . $fileName;
        }

        return $oldFile;
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }
}

