<?php

namespace Modules\Purchase\Services;

use Modules\Purchase\Repositories\SupplierPaymentRepository;
use Modules\Purchase\Repositories\SupplierRepository;
use Modules\Purchase\Models\SupplierPayment;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class SupplierPaymentService
{
    protected $supplierPaymentRepository;
    protected $supplierRepository;

    public function __construct(
        SupplierPaymentRepository $supplierPaymentRepository,
        SupplierRepository $supplierRepository
    ) {
        $this->supplierPaymentRepository = $supplierPaymentRepository;
        $this->supplierRepository = $supplierRepository;
    }

    /**
     * Get all supplier payments for the current company
     */
    public function getAllSupplierPayments(): Collection
    {
        return $this->supplierPaymentRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for supplier payments listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->supplierPaymentRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($supplierPayment, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $supplierPayment->id,
                'reference_no' => $supplierPayment->reference_no,
                'date' => formatDate($supplierPayment->date),
                'supplier_id' => $supplierPayment->supplier->name . ' (' . $supplierPayment->supplier->phone . ')'  ,
                'amount' => formatAmount($supplierPayment->amount),
                'payment_method_id' => $supplierPayment->account->name,
                'note' => truncateText($supplierPayment->note, 30, 10),
                'encrypted_id' => $supplierPayment->encrypted_id,
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
     * Get supplier payment by encrypted ID
     */
    public function getSupplierPaymentByEncryptedId(string $encryptedId): ?SupplierPayment
    {
        return $this->supplierPaymentRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new supplier payment
     */
    public function createSupplierPayment(array $data): SupplierPayment
    {
        $storeData = $this->prepareSupplierPaymentData($data);
        return $this->supplierPaymentRepository->create($storeData);
    }

    /**
     * Update an existing supplier payment
     */
    public function updateSupplierPayment(SupplierPayment $supplierPayment, array $data): bool
    {
        $updateData = $this->prepareSupplierPaymentData($data, true);
        return $this->supplierPaymentRepository->update($supplierPayment, $updateData);
    }

    /**
     * Delete a supplier payment
     */
    public function deleteSupplierPayment(SupplierPayment $supplierPayment): bool
    {
        return $this->supplierPaymentRepository->delete($supplierPayment);
    }

    /**
     * Get data for create/edit form
     */
    public function getFormData(?SupplierPayment $supplierPayment = null): array
    {
        $companyId = $this->getCompanyId();

        $data = [
            'suppliers' => $this->supplierRepository->getActiveSuppliers($companyId),
            'payment_methods' => PaymentMethod::where('del_status', 'Live')
                ->where('company_id', $companyId)
                ->where('status', 'Enable')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ];

        if ($supplierPayment) {
            $data['supplier_payment'] = $supplierPayment;
            $data['reference_no'] = $supplierPayment->reference_no;
        } else {
            $data['reference_no'] = $this->supplierPaymentRepository->generateReferenceNumber();
        }

        return $data;
    }

    /**
     * Get payments by supplier
     */
    public function getPaymentsBySupplier(int $supplierId): Collection
    {
        return $this->supplierPaymentRepository->getBySupplier($supplierId, $this->getCompanyId());
    }

    /**
     * Get total payments for a supplier
     */
    public function getTotalPaymentsBySupplier(int $supplierId): float
    {
        return $this->supplierPaymentRepository->getTotalPaymentsBySupplier($supplierId, $this->getCompanyId());
    }

    /**
     * Prepare supplier payment data for store/update
     */
    protected function prepareSupplierPaymentData(array $data, bool $isUpdate = false): array
    {
        $preparedData = [
            'reference_no' => $data['reference_no'],
            'date' => $data['date'],
            'supplier_id' => $data['supplier_id'],
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

