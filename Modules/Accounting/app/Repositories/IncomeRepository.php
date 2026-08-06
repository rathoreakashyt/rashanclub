<?php

namespace Modules\Accounting\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Accounting\Models\Income;

class IncomeRepository
{
    public function __construct(private Income $model)
    {
    }

    protected function baseQuery()
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'));
    }

    public function paginateWithFilters(int $perPage = 10, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->baseQuery()->with(['category:id,name', 'employee:id,name,phone', 'account:id,name']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('employee', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('employee', function ($q) use ($search) {
                        $q->where('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('account', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);
    }

    public function getLastForCompany(): ?Income
    {
        return $this->baseQuery()->orderBy('id', 'desc')->first();
    }

    public function create(array $data): Income
    {
        return $this->model->create($data);
    }

    public function find(int $id): ?Income
    {
        return $this->baseQuery()->find($id);
    }

    public function findByEncryptedId(string $encryptedId): ?Income
    {
        try {
            $id = (int) decrypt($encryptedId);
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update(Income $income, array $data): bool
    {
        return $income->update($data);
    }

    public function softDelete(Income $income): bool
    {
        return $income->update(['del_status' => 'Deleted']);
    }

    public function countLive(): int
    {
        return $this->baseQuery()->count();
    }
}


