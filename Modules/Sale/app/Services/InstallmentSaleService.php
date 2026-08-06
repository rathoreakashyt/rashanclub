<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\InstallmentSaleRepository;
use Modules\Sale\Models\InstallmentSale;
use Modules\Sale\Models\InstallmentSaleDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class InstallmentSaleService
{
    protected $installmentSaleRepository;

    public function __construct(InstallmentSaleRepository $installmentSaleRepository)
    {
        $this->installmentSaleRepository = $installmentSaleRepository;
    }

    /**
     * Get all installment sales for the current company
     */
    public function getAllInstallmentSales(): Collection
    {
        return $this->installmentSaleRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for installment sales listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->installmentSaleRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($sale, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $sale->id,
                'reference_no' => $sale->reference_no,
                'date' => formatDate($sale->date),
                'customer_name' => $sale->customer->name ?? 'N/A',
                'customer_phone' => $sale->customer->phone ?? 'N/A',
                'item_name' => $sale->item->name ?? 'N/A',
                'total' => formatAmount($sale->total),
                'down_payment' => formatAmount($sale->down_payment),
                'paid_amount' => formatAmount($sale->paid_amount),
                'due_amount' => formatAmount($sale->due_amount),
                'status' => $sale->status,
                'number_of_installment' => $sale->number_of_installment,
                'encrypted_id' => $sale->encrypted_id,
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
     * Get installment sale by encrypted ID
     */
    public function getInstallmentSaleByEncryptedId(string $encryptedId): ?InstallmentSale
    {
        return $this->installmentSaleRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Get installment sale by ID
     */
    public function getInstallmentSaleById(int $id): ?InstallmentSale
    {
        return $this->installmentSaleRepository->find($id);
    }

    /**
     * Generate reference number
     */
    public function generateReferenceNumber(): string
    {
        return $this->installmentSaleRepository->generateReferenceNumber($this->getCompanyId());
    }

    /**
     * Create a new installment sale
     */
    public function createInstallmentSale(array $data): InstallmentSale
    {
        $preparedData = $this->prepareInstallmentSaleData($data);
        return $this->installmentSaleRepository->create($preparedData);
    }

    /**
     * Update an existing installment sale
     */
    public function updateInstallmentSale(InstallmentSale $installmentSale, array $data): bool
    {
        $updateData = [
            'note' => $data['note'] ?? $installmentSale->note,
            'updated_at' => now(),
        ];

        return $this->installmentSaleRepository->update($installmentSale, $updateData);
    }

    /**
     * Delete an installment sale
     */
    public function deleteInstallmentSale(InstallmentSale $installmentSale): bool
    {
        return $this->installmentSaleRepository->delete($installmentSale);
    }

    /**
     * Prepare installment sale data for storage
     */
    protected function prepareInstallmentSaleData(array $data): array
    {
        $companyId = $this->getCompanyId();
        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');

        // Calculate amounts
        $price = (float) ($data['price'] ?? 0);
        $discount = $data['discount'] ?? '0';
        $discountAmount = $this->calculateDiscount($price, $discount);
        $percentageOfInterest = (float) ($data['percentage_of_interest'] ?? 0);
        $shippingOther = (float) ($data['shipping_other'] ?? 0);
        
        // Calculate total: (price - discount) + interest + shipping
        $priceAfterDiscount = $price - $discountAmount;
        $interestAmount = ($priceAfterDiscount * $percentageOfInterest) / 100;
        $total = $priceAfterDiscount + $interestAmount + $shippingOther;
        
        $downPayment = (float) ($data['down_payment'] ?? 0);
        $remaining = $total - $downPayment;

        // Prepare main sale data
        $saleData = [
            'reference_no' => $data['reference_no'] ?? $this->generateReferenceNumber(),
            'date' => $data['date'] ?? now()->toDateString(),
            'customer_id' => $data['customer_id'],
            'item_id' => $data['item_id'],
            'item_type' => $data['item_type'] ?? null,
            'expiry_imei_serial' => $data['expiry_imei_serial'] ?? null,
            'price' => $price,
            'discount' => $discount,
            'discount_amount' => $discountAmount,
            'number_of_installment' => (int) $data['number_of_installment'],
            'percentage_of_interest' => $percentageOfInterest,
            'interest_amount' => $interestAmount,
            'shipping_other' => $shippingOther,
            'total' => $total,
            'down_payment' => $downPayment,
            'remaining' => $remaining,
            'paid_amount' => $downPayment, // Initially paid is down payment
            'due_amount' => $remaining,
            'installment_type' => (int) ($data['installment_type'] ?? 30),
            'payment_method_id' => $data['payment_method_id'],
            'status' => 'Active',
            'note' => $data['note'] ?? null,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live',
        ];

        // Prepare installment details
        $installments = $this->prepareInstallmentDetails($data, $remaining, $companyId, $userId, $outletId);

        // Note: Down payment is saved in installment_sales table with payment_method_id
        // It is NOT saved as an installment detail or payment record

        return [
            'sale' => $saleData,
            'installments' => $installments,
        ];
    }

    /**
     * Prepare installment details
     */
    protected function prepareInstallmentDetails(array $data, float $remaining, int $companyId, int $userId, ?int $outletId): array
    {
        $installments = [];
        $numberOfInstallments = (int) $data['number_of_installment'];
        $installmentDuration = (int) ($data['installment_type'] ?? 30);
        $startDate = Carbon::parse($data['date'] ?? now()->toDateString());

        // If manual installments are provided
        if (!empty($data['amount_of_payment']) && is_array($data['amount_of_payment'])) {
            foreach ($data['amount_of_payment'] as $index => $amount) {
                $paymentDate = $data['payment_date'][$index] ?? $startDate->copy()->addDays($installmentDuration * ($index + 1))->toDateString();
                
                $installments[] = [
                    'amount_of_payment' => (float) $amount,
                    'amount' => (float) $amount, // For model casting compatibility
                    'payment_date' => $paymentDate,
                    'paid_status' => $data['paid_status'][$index] ?? 'Unpaid',
                    'paid_amount' => 0,
                    'remaining_amount' => (float) $amount,
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ];
            }
        } else {
            // Auto-generate installments
            $dividedAmount = floor($remaining / $numberOfInstallments);
            $firstInstallmentExtra = $remaining - ($dividedAmount * $numberOfInstallments);
            $firstInstallmentAmount = $dividedAmount + $firstInstallmentExtra;

            for ($i = 1; $i <= $numberOfInstallments; $i++) {
                $paymentDate = $startDate->copy()->addDays($installmentDuration * $i);
                $amount = $i === 1 ? $firstInstallmentAmount : $dividedAmount;

                $installments[] = [
                    'amount_of_payment' => $amount,
                    'amount' => $amount, // For model casting compatibility
                    'payment_date' => $paymentDate->toDateString(),
                    'paid_status' => 'Unpaid',
                    'paid_amount' => 0,
                    'remaining_amount' => $amount,
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ];
            }
        }

        return $installments;
    }

    /**
     * Calculate discount amount
     */
    protected function calculateDiscount(float $price, string $discount): float
    {
        if (empty($discount) || $discount === '0') {
            return 0;
        }

        // Check if percentage discount (e.g., "10%")
        if (strpos($discount, '%') !== false) {
            $percentage = (float) str_replace('%', '', $discount);
            return ($price * $percentage) / 100;
        }

        // Fixed amount discount
        return (float) $discount;
    }

    /**
     * Record payment for an installment
     * Updates installment detail only, no separate payment records
     */
    public function recordInstallmentPayment(int $detailId, array $paymentData): InstallmentSaleDetail
    {
        $paymentData['user_id'] = Auth::id();
        $paymentData['outlet_id'] = session('outlet.outlet_id');
        $paymentData['company_id'] = $this->getCompanyId();
        $paymentData['payment_date'] = $paymentData['payment_date'] ?? now()->toDateString();
        $paymentData['del_status'] = 'Live';
        
        // Handle paid_date if provided (from payment form)
        if (isset($paymentData['paid_date']) && $paymentData['paid_date']) {
            // Keep paid_date for repository to use
        } else {
            // Will be set in repository based on payment status
            unset($paymentData['paid_date']);
        }

        return $this->installmentSaleRepository->recordInstallmentPayment($detailId, $paymentData);
    }

    /**
     * Get installment details for a sale
     */
    public function getInstallmentDetails(int $installmentSaleId): Collection
    {
        return $this->installmentSaleRepository->getInstallmentDetails($installmentSaleId);
    }

    /**
     * Get overdue installments
     */
    public function getOverdueInstallments(): Collection
    {
        return $this->installmentSaleRepository->getOverdueInstallments($this->getCompanyId());
    }

    /**
     * Get payments for a sale
     */
    public function getPayments(int $installmentSaleId): Collection
    {
        return $this->installmentSaleRepository->getPayments($installmentSaleId);
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        return $this->installmentSaleRepository->getStatistics($this->getCompanyId());
    }

    /**
     * Get installment detail by ID
     */
    public function getInstallmentDetailById(int $detailId): ?InstallmentSaleDetail
    {
        return $this->installmentSaleRepository->findInstallmentDetail($detailId);
    }

    /**
     * Get all due installments for a customer
     */
    public function getCustomerDueInstallments(int $customerId): Collection
    {
        $companyId = $this->getCompanyId();
        
        return InstallmentSaleDetail::with(['installmentSale.item'])
            ->whereHas('installmentSale', function ($q) use ($customerId, $companyId) {
                $q->where('customer_id', $customerId)
                  ->where('company_id', $companyId)
                  ->where('del_status', 'Live');
            })
            ->where('paid_status', '!=', 'Paid')
            ->where('del_status', 'Live')
            ->orderBy('payment_date')
            ->get();
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }
}

