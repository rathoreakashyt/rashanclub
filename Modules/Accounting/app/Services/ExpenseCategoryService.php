<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Repositories\ExpenseCategoryRepository;

class ExpenseCategoryService
{
    public function __construct(private ExpenseCategoryRepository $expenseCategoryRepository)
    {
    }

    public function getDataTable(int $length, int $start, ?string $search): array
    {
        $page = $length > 0 ? (int) floor($start / $length) + 1 : 1;
        $paginator = $this->expenseCategoryRepository->paginateWithFilters($length, $page, $search);

        $recordsTotal = $this->expenseCategoryRepository->countLive();
        $filteredCount = $paginator->total();
        $startingNumber = $filteredCount - $start;

        $data = collect($paginator->items())->map(function ($category, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'encrypted_id' => $category->encrypted_id,
            ];
        })->values();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ];
    }

    public function create(array $data)
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';

        return $this->expenseCategoryRepository->create($data);
    }

    public function getByEncryptedId(string $encryptedId)
    {
        $category = $this->expenseCategoryRepository->findByEncryptedId($encryptedId);
        if (!$category) {
            throw new \Exception('Expense Category not found');
        }

        return $category;
    }

    public function update(string $encryptedId, array $data)
    {
        $category = $this->getByEncryptedId($encryptedId);
        return $this->expenseCategoryRepository->update($category, $data);
    }

    public function delete(string $encryptedId)
    {
        $category = $this->getByEncryptedId($encryptedId);
        return $this->expenseCategoryRepository->softDelete($category);
    }

    public function listForSelect()
    {
        return $this->expenseCategoryRepository->listForSelect();
    }
}


