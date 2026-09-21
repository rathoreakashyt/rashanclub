<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\PromotionRepository;
use Modules\Sale\Models\Promotion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class PromotionService
{
    protected $promotionRepository;

    public function __construct(PromotionRepository $promotionRepository)
    {
        $this->promotionRepository = $promotionRepository;
    }

    /**
     * Get all promotions for the current company
     */
    public function getAllPromotions(): Collection
    {
        return $this->promotionRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for promotions listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->promotionRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($promotion, $index) use ($startingNumber) {
            $type = $promotion->type == 1 ? 'Discount' : ($promotion->type == 2 ? 'Coupon Discount' : 'Free Item');
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $promotion->id,
                'title' => $promotion->title,
                'type' => $type,
                'start_date' => formatDate($promotion->start_date),
                'end_date' => formatDate($promotion->end_date),
                'status' => $promotion->status,
                'encrypted_id' => $promotion->encrypted_id,
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
     * Get promotion by encrypted ID
     */
    public function getPromotionByEncryptedId(string $encryptedId): ?Promotion
    {
        return $this->promotionRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new promotion
     */
    public function createPromotion(array $data): Promotion
    {
        $hasOverlap = $this->promotionRepository->checkOverlappingPromotions(
            $this->getCompanyId(),
            $this->getOutletId(),
            $data['start_date'],
            $data['end_date'],
            ($data['type'] == 1 || $data['type'] == 3) ? $data['item_id'] : null,
            null,
            (int) $data['type'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null
        );

        if ($hasOverlap) {
            throw new \Exception('A promotion already exists for this date range and item');
        }

        $storeData = $this->preparePromotionData($data);
        return $this->promotionRepository->create($storeData);
    }

    /**
     * Update an existing promotion
     */
    public function updatePromotion(Promotion $promotion, array $data): bool
    {
        $hasOverlap = $this->promotionRepository->checkOverlappingPromotions(
            $this->getCompanyId(),
            $this->getOutletId(),
            $data['start_date'],
            $data['end_date'],
            ($data['type'] == 1 || $data['type'] == 3) ? $data['item_id'] : null,
            $promotion->id,
            (int) $data['type'],
            $data['start_time'] ?? null,
            $data['end_time'] ?? null
        );

        if ($hasOverlap) {
            throw new \Exception('A promotion already exists for this date range and food menu');
        }

        $updateData = $this->preparePromotionData($data, true);
        return $this->promotionRepository->update($promotion, $updateData);
    }

    /**
     * Delete a promotion
     */
    public function deletePromotion(Promotion $promotion): bool
    {
        return $this->promotionRepository->delete($promotion);
    }

    /**
     * Prepare promotion data for store/update
     */
    protected function preparePromotionData(array $data, bool $isUpdate = false): array
    {
        // Normalize type: string → integer
        $type = $data['type'];
        if (!is_numeric($type)) {
            $type = match(strtolower(trim($type))) {
                'discount' => 1,
                'coupon discount', 'coupon discount (on entire sale)', 'coupon' => 2,
                'free item', 'free_quantity', 'buy x get y' => 3,
                default => 1,
            };
        }
        $data['type'] = (int) $type;

        // Normalize status: string → integer
        $status = $data['status'] ?? '1';
        if (!is_numeric($status)) {
            $status = match(strtolower(trim($status))) {
                'active', 'enable', 'yes' => 1,
                'inactive', 'disable', 'no' => 2,
                default => 1,
            };
        }
        $data['status'] = (int) $status;

        // Normalize scheme_basis
        $basis = strtolower(trim($data['scheme_basis'] ?? 'item'));
        $data['scheme_basis'] = match(true) {
            str_contains($basis, 'bill') => 'bill',
            str_contains($basis, 'party') => 'party',
            str_contains($basis, 'item') => 'item',
            default => 'item',
        };

        $preparedData = [
            'type' => $data['type'],
            'scheme_basis' => $data['scheme_basis'] ?? 'item',
            'title' => $data['title'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'status' => $data['status'],
            'outlet_id' => $this->getOutletId(),
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
            'min_purchase_amount' => $data['min_purchase_amount'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? 0,
            'applicable_items' => !empty($data['applicable_items']) ? $data['applicable_items'] : null,
            'applicable_categories' => !empty($data['applicable_categories']) ? $data['applicable_categories'] : null,
            'applicable_customers' => !empty($data['applicable_customers']) ? $data['applicable_customers'] : null,
            'applicable_customer_types' => !empty($data['applicable_customer_types']) ? $data['applicable_customer_types'] : null,
            'tier_percentages' => $this->prepareTierPercentages($data['tier_percentages'] ?? null),
            'flavour_alternatives' => $this->prepareFlavourAlternatives($data['flavour_alternatives'] ?? null),
        ];

        // Reset all type-specific fields to null first
        $preparedData['discount'] = null;
        $preparedData['coupon_code'] = null;
        $preparedData['item_id'] = null;
        $preparedData['qty'] = null;
        $preparedData['get_item_id'] = null;
        $preparedData['get_qty'] = null;
        $preparedData['bill_level_discount'] = 0;
        $preparedData['bill_level_discount_type'] = null;

        // Then set only the fields needed for the current type
        if ($data['type'] == 1) {
            $preparedData['item_id'] = $data['item_id'];
            $preparedData['discount'] = $data['discount'];
        } elseif ($data['type'] == 2) {
            $preparedData['discount'] = $data['discount'];
            $preparedData['coupon_code'] = $data['coupon_code'];
        } elseif ($data['type'] == 3) {
            $preparedData['item_id'] = $data['item_id'];
            $preparedData['qty'] = $data['qty'];
            $preparedData['get_item_id'] = $data['get_item_id'];
            $preparedData['get_qty'] = $data['get_qty'];
        }

        // For bill-level scheme (type 1 or 2 with scheme_basis = 'bill')
        if (($data['type'] == 1 || $data['type'] == 2) && ($data['scheme_basis'] ?? 'item') === 'bill') {
            $preparedData['bill_level_discount'] = $data['bill_level_discount'] ?? 0;
            $preparedData['bill_level_discount_type'] = $data['bill_level_discount_type'] ?? $this->detectDiscountType($data['bill_level_discount'] ?? '');
            $preparedData['item_id'] = null;
        }

        if ($isUpdate) {
            $preparedData['updated_at'] = now();
        } else {
            $preparedData['del_status'] = 'Live';
        }

        return $preparedData;
    }

    protected function detectDiscountType(string $discount): string
    {
        return str_contains($discount, '%') ? 'percentage' : 'fixed';
    }

    /**
     * Prepare tier pricing (partial quantity payment) for Buy X Get Y schemes.
     * Each tier is either a percentage of list price or a fixed amount per unit:
     * Input: JSON string or array like [{"qty":1,"percent":60},{"qty":2,"custom_price":85.5}]
     * Output: sanitized array or null
     */
    protected function prepareTierPercentages($tierPercentages): ?array
    {
        if (empty($tierPercentages)) {
            return null;
        }

        if (is_string($tierPercentages)) {
            $decoded = json_decode($tierPercentages, true);
            $tierPercentages = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($tierPercentages)) {
            return null;
        }

        $prepared = [];
        foreach ($tierPercentages as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $qty = (int) ($tier['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $hasCustomPrice = array_key_exists('custom_price', $tier) && $tier['custom_price'] !== '' && $tier['custom_price'] !== null;
            if ($hasCustomPrice) {
                $price = (float) $tier['custom_price'];
                if ($price >= 0) {
                    $prepared[] = ['qty' => $qty, 'custom_price' => $price];
                }
            } else {
                $percent = (float) ($tier['percent'] ?? 0);
                if ($percent >= 0 && $percent <= 100) {
                    $prepared[] = ['qty' => $qty, 'percent' => $percent];
                }
            }
        }

        usort($prepared, fn ($a, $b) => $a['qty'] <=> $b['qty']);

        return empty($prepared) ? null : $prepared;
    }

    /**
     * Prepare flavour alternatives for Buy X Get Y free items.
     * Input: JSON string or array like [{"get_item_id":123,"name":"Chocolate"}]
     * Output: sanitized array or null
     */
    protected function prepareFlavourAlternatives($flavourAlternatives): ?array
    {
        if (empty($flavourAlternatives)) {
            return null;
        }

        if (is_string($flavourAlternatives)) {
            $decoded = json_decode($flavourAlternatives, true);
            $flavourAlternatives = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($flavourAlternatives)) {
            return null;
        }

        $prepared = [];
        foreach ($flavourAlternatives as $flavour) {
            if (!is_array($flavour)) {
                continue;
            }
            $itemId = (int) ($flavour['get_item_id'] ?? $flavour['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $name = trim($flavour['name'] ?? '');
            $prepared[] = [
                'get_item_id' => $itemId,
                'name' => $name ?: 'Flavour ' . count($prepared) + 1,
            ];
        }

        return empty($prepared) ? null : $prepared;
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }

    /**
     * Get current outlet ID from session
     */
    protected function getOutletId(): int
    {
        return session('outlet.outlet_id');
    }
}

