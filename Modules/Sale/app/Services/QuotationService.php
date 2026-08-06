<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\QuotationRepository;
use Modules\Sale\Models\Quotation;
use Modules\Sale\Models\QuotationDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class QuotationService
{
    protected $quotationRepository;

    public function __construct(QuotationRepository $quotationRepository)
    {
        $this->quotationRepository = $quotationRepository;
    }

    /**
     * Get all quotations for the current company
     */
    public function getAllQuotations(): Collection
    {
        return $this->quotationRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for quotations listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->quotationRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($quotation, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $quotation->id,
                'reference_no' => $quotation->reference_no,
                'date' => formatDate($quotation->date),
                'customer_name' => $quotation->customer->name ?? '',
                'customer_phone' => $quotation->customer->phone ?? '',
                'grand_total' => formatAmount($quotation->grand_total),
                'encrypted_id' => $quotation->encrypted_id,
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
     * Get quotation by encrypted ID
     */
    public function getQuotationByEncryptedId(string $encryptedId): ?Quotation
    {
        return $this->quotationRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new quotation
     */
    public function createQuotation(array $data): Quotation
    {
        $preparedData = $this->prepareQuotationData($data);
        return $this->quotationRepository->create($preparedData);
    }

    /**
     * Update an existing quotation
     */
    public function updateQuotation(Quotation $quotation, array $data): bool
    {
        $preparedData = $this->prepareQuotationData($data);
        return $this->quotationRepository->update($quotation, $preparedData);
    }

    /**
     * Delete a quotation
     */
    public function deleteQuotation(Quotation $quotation): bool
    {
        return $this->quotationRepository->delete($quotation);
    }

    /**
     * Generate reference number
     */
    public function generateReferenceNumber(): string
    {
        return $this->quotationRepository->generateReferenceNumber();
    }

    /**
     * Prepare quotation data for storage
     */
    protected function prepareQuotationData(array $data): array
    {
        $companyId = $this->getCompanyId();
        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');

        // Calculate discount amount
        $subtotal = 0;
        $items = $data['items'] ?? [];
        $quantities = $data['quantity'] ?? [];
        $unitPrices = $data['unit_price'] ?? [];
        $totals = $data['total'] ?? [];
        $descriptions = $data['description'] ?? [];

        // Calculate subtotal from item totals
        foreach ($totals as $total) {
            $subtotal += (float) $total;
        }

        // Calculate discount
        $discountValue = $data['discount'] ?? '0';
        $discountAmount = $this->calculateDiscount($subtotal, $discountValue);

        // Calculate grand total
        $grandTotal = $subtotal - $discountAmount;

        // Prepare main quotation data
        $quotationData = [
            'date' => $data['date'] ?? now()->toDateString(),
            'reference_no' => $data['reference_no'] ?? $this->generateReferenceNumber(),
            'customer_id' => $data['customer_id'],
            'discount' => $discountValue,
            'grand_total' => $grandTotal,
            'note' => $data['note'] ?? null,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live',
        ];

        // Prepare quotation details
        $details = [];
        foreach ($items as $index => $itemId) {
            if (!empty($itemId)) {
                $details[] = [
                    'item_id' => $itemId,
                    'unit_price' => (float) ($unitPrices[$index] ?? 0),
                    'quantity' => (int) ($quantities[$index] ?? 1),
                    'total' => (float) ($totals[$index] ?? 0),
                    'description' => $descriptions[$index] ?? null,
                    'user_id' => $userId,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ];
            }
        }

        return [
            'quotation' => $quotationData,
            'details' => $details,
        ];
    }

    /**
     * Calculate discount amount
     */
    protected function calculateDiscount(float $subtotal, string $discount): float
    {
        if (empty($discount) || $discount === '0') {
            return 0;
        }

        if (strpos($discount, '%') !== false) {
            // Percentage discount
            $percent = (float) str_replace('%', '', $discount);
            return $subtotal * ($percent / 100);
        } else {
            // Fixed amount discount
            return (float) $discount;
        }
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }
}

