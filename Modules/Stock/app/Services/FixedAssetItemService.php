<?php

namespace Modules\Stock\Services;

use Modules\Stock\Repositories\FixedAssetItemRepository;
use Modules\Stock\Models\FixedAssetItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class FixedAssetItemService
{
    protected $itemRepository;

    public function __construct(FixedAssetItemRepository $itemRepository)
    {
        $this->itemRepository = $itemRepository;
    }

    /**
     * Get all fixed asset items for the current company
     */
    public function getAllItems(): Collection
    {
        return $this->itemRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for fixed asset items listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->itemRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($item, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $item->id,
                'name' => $item->name,
                'description' => $item->description ?? '',
                'encrypted_id' => $item->encrypted_id,
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
     * Get fixed asset item by encrypted ID
     */
    public function getItemByEncryptedId(string $encryptedId): ?FixedAssetItem
    {
        return $this->itemRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new fixed asset item
     */
    public function createItem(array $data): FixedAssetItem
    {
        $preparedData = $this->prepareItemData($data);
        return $this->itemRepository->create($preparedData);
    }

    /**
     * Update an existing fixed asset item
     */
    public function updateItem(FixedAssetItem $item, array $data): bool
    {
        $preparedData = $this->prepareItemData($data);
        return $this->itemRepository->update($item, $preparedData);
    }

    /**
     * Delete a fixed asset item
     */
    public function deleteItem(FixedAssetItem $item): bool
    {
        return $this->itemRepository->delete($item);
    }

    /**
     * Prepare item data for storage
     */
    protected function prepareItemData(array $data): array
    {
        $companyId = $this->getCompanyId();
        $userId = Auth::id();
        $outletId = session('outlet.outlet_id');

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'user_id' => $userId,
            'outlet_id' => $outletId,
            'company_id' => $companyId,
            'del_status' => 'Live',
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

