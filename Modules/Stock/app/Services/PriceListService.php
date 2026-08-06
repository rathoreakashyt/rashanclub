<?php

namespace Modules\Stock\Services;

use Modules\Stock\Repositories\PriceListRepository;
use Modules\Stock\Models\PriceList;
use Modules\Stock\Models\PriceListItem;
use Illuminate\Support\Facades\Auth;

class PriceListService
{
    protected $priceListRepository;

    public function __construct(PriceListRepository $priceListRepository)
    {
        $this->priceListRepository = $priceListRepository;
    }

    public function getAll(int $companyId)
    {
        return $this->priceListRepository->all($companyId);
    }

    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->priceListRepository->getDataTableData(
            $this->getCompanyId(), $start, $length, $search
        );

        $startingNumber = $result['filteredCount'] - $start;
        $transformedData = $result['data']->map(function ($pl, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $pl->id,
                'name' => $pl->name,
                'description' => $pl->description,
                'customer_type' => $pl->customer_type,
                'item_count' => $pl->items()->where('del_status', 'Live')->count(),
                'encrypted_id' => $pl->encrypted_id,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['filteredCount'],
            'data' => $transformedData,
        ];
    }

    public function getCreateData(): array
    {
        return [];
    }

    public function getEditData(PriceList $priceList): PriceList
    {
        $priceList->load(['items' => function($q) {
            $q->where('del_status', 'Live');
        }]);
        return $priceList;
    }

    public function createPriceList(array $data): PriceList
    {
        $storeData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'customer_type' => $data['customer_type'] ?? null,
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
            'del_status' => 'Live',
        ];
        $priceList = $this->priceListRepository->create($storeData);

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $itemData) {
                if (!empty($itemData['item_id']) && isset($itemData['price'])) {
                    PriceListItem::create([
                        'price_list_id' => $priceList->id,
                        'item_id' => $itemData['item_id'],
                        'price' => $itemData['price'],
                        'user_id' => Auth::id(),
                        'company_id' => $this->getCompanyId(),
                        'del_status' => 'Live',
                    ]);
                }
            }
        }

        return $priceList;
    }

    public function updatePriceList(PriceList $priceList, array $data): bool
    {
        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'customer_type' => $data['customer_type'] ?? null,
            'updated_at' => now(),
        ];
        $result = $this->priceListRepository->update($priceList, $updateData);

        PriceListItem::where('price_list_id', $priceList->id)->update(['del_status' => 'Deleted']);

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $itemData) {
                if (!empty($itemData['item_id']) && isset($itemData['price'])) {
                    PriceListItem::create([
                        'price_list_id' => $priceList->id,
                        'item_id' => $itemData['item_id'],
                        'price' => $itemData['price'],
                        'user_id' => Auth::id(),
                        'company_id' => $this->getCompanyId(),
                        'del_status' => 'Live',
                    ]);
                }
            }
        }

        return $result;
    }

    public function deletePriceList(PriceList $priceList): bool
    {
        return $this->priceListRepository->delete($priceList);
    }

    public function getItemPrice(int $priceListId, int $itemId): ?float
    {
        $item = PriceListItem::where('price_list_id', $priceListId)
            ->where('item_id', $itemId)
            ->where('del_status', 'Live')
            ->first();
        return $item ? (float) $item->price : null;
    }

    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }
}
