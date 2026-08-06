<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\InstallmentSale;
use Modules\Sale\Models\InstallmentSaleDetail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InstallmentSaleRepository
{
    protected $model;
    protected $detailModel;

    public function __construct(
        InstallmentSale $model,
        InstallmentSaleDetail $detailModel
    ) {
        $this->model = $model;
        $this->detailModel = $detailModel;
    }

    /**
     * Get all installment sales for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->with(['customer', 'item', 'user'])
            ->live()
            ->forCompany($companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get installment sales with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->with(['customer', 'item'])
            ->live()
            ->forCompany($companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('item', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $sales = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $sales,
        ];
    }

    /**
     * Find installment sale by ID
     */
    public function find(int $id): ?InstallmentSale
    {
        
        return $this->model
            ->with(['customer', 'item', 'user', 'installmentDetails'])
            ->live()
            ->find($id);
    }

    /**
     * Find installment sale by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?InstallmentSale
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Create a new installment sale with details and payment
     */
    public function create(array $data): InstallmentSale
    {
        return DB::transaction(function () use ($data) {
            // Create the main installment sale
            $installmentSale = $this->model->create($data['sale']);

            // Create installment details (down payment is saved in installment_sales table, not as a detail)
            foreach ($data['installments'] as $installment) {
                $installment['installment_sale_id'] = $installmentSale->id;
                $this->detailModel->create($installment);
            }

            return $installmentSale->load(['customer', 'item', 'installmentDetails']);
        });
    }

    /**
     * Update an installment sale
     */
    public function update(InstallmentSale $installmentSale, array $data): bool
    {
        return $installmentSale->update($data);
    }

    /**
     * Soft delete an installment sale
     */
    public function delete(InstallmentSale $installmentSale): bool
    {
        return DB::transaction(function () use ($installmentSale) {
            // Mark details as deleted
            $this->detailModel
                ->where('installment_sale_id', $installmentSale->id)
                ->update(['del_status' => 'Deleted']);

            // Mark main sale as deleted
            return $installmentSale->update(['del_status' => 'Deleted']);
        });
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNumber(int $companyId): string
    {
        $prefix = 'INS-' . date('Y') . '-';
        $lastSale = $this->model
            ->where('reference_no', 'like', $prefix . '%')
            ->forCompany($companyId)
            ->orderBy('reference_no', 'desc')
            ->first();

        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->reference_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get installment details by sale ID
     */
    public function getInstallmentDetails(int $installmentSaleId): Collection
    {
        return $this->detailModel
            ->with(['paymentMethod'])
            ->where('installment_sale_id', $installmentSaleId)
            ->live()
            ->orderBy('payment_date')
            ->get();
    }

    /**
     * Get installment detail by ID
     */
    public function findInstallmentDetail(int $detailId): ?InstallmentSaleDetail
    {
        return $this->detailModel
            ->with(['installmentSale', 'paymentMethod'])
            ->live()
            ->find($detailId);
    }

    /**
     * Record installment payment - updates installment detail only
     * No separate payment records are created
     */
    public function recordInstallmentPayment(int $detailId, array $paymentData): InstallmentSaleDetail
    {
        return DB::transaction(function () use ($detailId, $paymentData) {
            $detail = $this->detailModel->find($detailId);
            
            if (!$detail) {
                throw new \Exception('Installment detail not found');
            }

            // Update detail payment info
            $newPaidAmount = $detail->paid_amount + $paymentData['amount'];
            $newRemainingAmount = $detail->amount - $newPaidAmount;
            $paidStatus = $newRemainingAmount <= 0 ? 'Paid' : ($newPaidAmount > 0 ? 'Partial' : 'Unpaid');
            
            // Use provided paid_date or set based on status
            $paidDate = null;
            if (isset($paymentData['paid_date']) && $paymentData['paid_date']) {
                $paidDate = $paymentData['paid_date'];
            } elseif ($paidStatus === 'Paid') {
                $paidDate = now()->toDateString();
            } else {
                $paidDate = $detail->paid_date;
            }

            $detail->update([
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => max(0, $newRemainingAmount),
                'paid_status' => $paidStatus,
                'paid_date' => $paidDate,
                'payment_method_id' => $paymentData['payment_method_id'],
            ]);

            // Update main sale paid/due amounts
            $this->updateSalePaidAmount($detail->installment_sale_id);

            return $detail;
        });
    }

    /**
     * Update sale paid amount based on down payment and installment details
     * Calculates from installment_sales.down_payment + sum of paid_amount from installment_sale_details
     */
    public function updateSalePaidAmount(int $installmentSaleId): void
    {
        $sale = $this->model->find($installmentSaleId);
        if (!$sale) {
            return;
        }

        // Get down payment from installment_sales table
        $downPayment = (float) ($sale->down_payment ?? 0);
        
        // Get sum of all paid amounts from installment_sale_details table
        $installmentPayments = $this->detailModel
            ->where('installment_sale_id', $installmentSaleId)
            ->live()
            ->sum('paid_amount');

        // Total paid = down payment + installment payments
        $totalPaid = $downPayment + $installmentPayments;

        $sale->update([
            'paid_amount' => $totalPaid,
            'due_amount' => max(0, $sale->total - $totalPaid),
            'status' => $totalPaid >= $sale->total ? 'Completed' : 'Active',
        ]);
    }

    /**
     * Get overdue installments for a company
     */
    public function getOverdueInstallments(int $companyId): Collection
    {
        return $this->detailModel
            ->with(['installmentSale.customer', 'installmentSale.item'])
            ->whereHas('installmentSale', function ($q) use ($companyId) {
                $q->live()->forCompany($companyId);
            })
            ->live()
            ->overdue()
            ->orderBy('payment_date')
            ->get();
    }

    /**
     * Get payments for an installment sale
     * Returns installment details that have been paid (paid_amount > 0)
     */
    public function getPayments(int $installmentSaleId): Collection
    {
        return $this->detailModel
            ->with(['paymentMethod', 'user'])
            ->where('installment_sale_id', $installmentSaleId)
            ->where('paid_amount', '>', 0)
            ->live()
            ->orderBy('paid_date', 'desc')
            ->get();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(int $companyId): array
    {
        $totalSales = $this->model->live()->forCompany($companyId)->count();
        $activeSales = $this->model->live()->forCompany($companyId)->active()->count();
        $totalValue = $this->model->live()->forCompany($companyId)->sum('total');
        $totalCollected = $this->model->live()->forCompany($companyId)->sum('paid_amount');
        $totalDue = $this->model->live()->forCompany($companyId)->sum('due_amount');

        return [
            'total_sales' => $totalSales,
            'active_sales' => $activeSales,
            'total_value' => $totalValue,
            'total_collected' => $totalCollected,
            'total_due' => $totalDue,
        ];
    }
}

