<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\CustomerReceiveRepository;
use Modules\Sale\Repositories\CustomerRepository;
use Modules\Sale\Models\CustomerReceive;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class CustomerReceiveService
{
    protected $customerReceiveRepository;
    protected $customerRepository;

    public function __construct(
        CustomerReceiveRepository $customerReceiveRepository,
        CustomerRepository $customerRepository
    ) {
        $this->customerReceiveRepository = $customerReceiveRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get all customer receives for the current company
     */
    public function getAllCustomerReceives(): Collection
    {
        return $this->customerReceiveRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for customer receives listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->customerReceiveRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($customerReceive, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $customerReceive->id,
                'reference_no' => $customerReceive->reference_no,
                'date' => formatDate($customerReceive->date),
                'customer_id' => $customerReceive->customer 
                    ? $customerReceive->customer->name 
                    . ($customerReceive->customer->phone ? ' (' . $customerReceive->customer->phone . ')' : '') 
                    : '',
                'amount' => formatAmount($customerReceive->amount),
                'payment_method_id' => $customerReceive->payment->name ?? '',
                'note' => truncateText($customerReceive->note, 50, 20),
                'encrypted_id' => $customerReceive->encrypted_id,
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
     * Get customer receive by encrypted ID
     */
    public function getCustomerReceiveByEncryptedId(string $encryptedId): ?CustomerReceive
    {
        return $this->customerReceiveRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new customer receive
     */
    public function createCustomerReceive(array $data): CustomerReceive
    {
        $storeData = $this->prepareCustomerReceiveData($data);
        return $this->customerReceiveRepository->create($storeData);
    }

    /**
     * Update an existing customer receive
     */
    public function updateCustomerReceive(CustomerReceive $customerReceive, array $data): bool
    {
        $updateData = $this->prepareCustomerReceiveData($data, true);
        return $this->customerReceiveRepository->update($customerReceive, $updateData);
    }

    /**
     * Delete a customer receive
     */
    public function deleteCustomerReceive(CustomerReceive $customerReceive): bool
    {
        return $this->customerReceiveRepository->delete($customerReceive);
    }

    /**
     * Get data for create/edit form
     */
    public function getFormData(?CustomerReceive $customerReceive = null): array
    {
        $companyId = $this->getCompanyId();

        $data = [
            'customers' => $this->customerRepository->getActiveCustomers($companyId),
            'payment_methods' => PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ];

        if ($customerReceive) {
            $data['customer_receive'] = $customerReceive;
            $data['reference_no'] = $customerReceive->reference_no;
        } else {
            $data['reference_no'] = $this->customerReceiveRepository->generateReferenceNumber();
        }

        return $data;
    }

    /**
     * Get receives by customer
     */
    public function getReceivesByCustomer(int $customerId): Collection
    {
        return $this->customerReceiveRepository->getByCustomer($customerId, $this->getCompanyId());
    }

    /**
     * Get total receives for a customer
     */
    public function getTotalReceivesByCustomer(int $customerId): float
    {
        return $this->customerReceiveRepository->getTotalReceivesByCustomer($customerId, $this->getCompanyId());
    }

    /**
     * Prepare customer receive data for store/update
     */
    protected function prepareCustomerReceiveData(array $data, bool $isUpdate = false): array
    {
        $preparedData = [
            'reference_no' => $data['reference_no'],
            'date' => $data['date'],
            'customer_id' => $data['customer_id'],
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'payment_method_id' => $data['payment_method_id'],
            'del_status' => 'Live',
        ];

        if (!$isUpdate) {
            $preparedData['user_id'] = Auth::id();
            $preparedData['outlet_id'] = session('outlet.outlet_id') ?? 1;
            $preparedData['company_id'] = $this->getCompanyId();
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

