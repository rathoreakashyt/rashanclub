<?php

namespace Modules\Stock\Repositories;

use Modules\Stock\Models\PriceList;

class PriceListRepository
{
    protected $model;

    public function __construct(PriceList $model)
    {
        $this->model = $model;
    }

    public function all(int $companyId)
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getDataTableData(int $companyId, int $start, int $length, string $search): array
    {
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);

        $query = clone $baseQuery;
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $data = $query->orderBy('id', 'desc')->skip($start)->take($length)->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $data,
        ];
    }

    public function find(int $id): ?PriceList
    {
        return $this->model->where('del_status', 'Live')->find($id);
    }

    public function findByEncryptedId(string $encryptedId): ?PriceList
    {
        return $this->find(decrypt($encryptedId));
    }

    public function create(array $data): PriceList
    {
        return $this->model->create($data);
    }

    public function update(PriceList $priceList, array $data): bool
    {
        return $priceList->update($data);
    }

    public function delete(PriceList $priceList): bool
    {
        return $priceList->update(['del_status' => 'Deleted']);
    }

    public function listForSelect(int $companyId): array
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->pluck('name', 'id')
            ->toArray();
    }
}
