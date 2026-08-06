<?php

namespace Modules\Accounting\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Accounting\Models\ExpenseCategory;

class ExpenseCategoryRepository
{
    public function __construct(private ExpenseCategory $model)
    {
    }

    protected function baseQuery()
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'));
    }

    /**
     * Paginate categories for datatable usage.
     */
    public function paginateWithFilters(int $perPage = 10, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->baseQuery()->orderBy('id', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * List categories for selects.
     */
    public function listForSelect(): Collection
    {
        return $this->baseQuery()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): ExpenseCategory
    {
        return $this->model->create($data);
    }

    public function find(int $id): ?ExpenseCategory
    {
        return $this->baseQuery()->find($id);
    }

    public function findByEncryptedId(string $encryptedId): ?ExpenseCategory
    {
        try {
            $id = (int) decrypt($encryptedId);
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update(ExpenseCategory $category, array $data): bool
    {
        return $category->update($data);
    }

    public function softDelete(ExpenseCategory $category): bool
    {
        return $category->update(['del_status' => 'Deleted']);
    }

    public function countLive(): int
    {
        return $this->baseQuery()->count();
    }
}


