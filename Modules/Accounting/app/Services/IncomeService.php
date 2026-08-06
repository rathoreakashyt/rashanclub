<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Repositories\IncomeRepository;
use Modules\Accounting\Repositories\IncomeCategoryRepository;
use Modules\Accounting\Repositories\PaymentMethodRepository;

class IncomeService
{
    public function __construct(
        private IncomeRepository $incomeRepository,
        private IncomeCategoryRepository $incomeCategoryRepository,
        private PaymentMethodRepository $paymentMethodRepository
    ) {
    }

    public function getDataTable(int $length, int $start, ?string $search): array
    {
        $page = $length > 0 ? (int) floor($start / $length) + 1 : 1;
        $paginator = $this->incomeRepository->paginateWithFilters($length, $page, $search);

        $recordsTotal = $this->incomeRepository->countLive();
        $filteredCount = $paginator->total();
        $startingNumber = $filteredCount - $start;

        $data = collect($paginator->items())->map(function ($income, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $income->id,
                'reference_no' => $income->reference_no,
                'date' => formatDate($income->date),
                'category_id' => $income->category?->name ?? '',
                'amount' => formatAmount($income->amount),
                'account' => $income->account?->name ?? '',
                'employee_id' => $income->employee?->name ?? '',
                'note' => truncateText($income->note, 30, 10),
                'encrypted_id' => $income->encrypted_id,
            ];
        })->values();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ];
    }

    public function getCreateData(): array
    {
        $lastRecord = $this->incomeRepository->getLastForCompany();
        $nextId = $lastRecord ? $lastRecord->id + 1 : 1;

        return [
            'reference_no' => str_pad($nextId, 6, '0', STR_PAD_LEFT),
            'income_categories' => $this->incomeCategoryRepository->listForSelect(),
            'employees' => User::where('del_status', 'Live')->select('id', 'name')->orderBy('name')->get(),
            'payment_methods' => $this->paymentMethodRepository->listForSelect(),
        ];
    }

    public function getEditData(string $encryptedId): array
    {
        $income = $this->incomeRepository->findByEncryptedId($encryptedId);
        if (!$income) {
            throw new \Exception('Income not found');
        }

        return array_merge($this->getCreateData(), [
            'income' => $income,
            'reference_no' => $income->reference_no,
        ]);
    }

    public function create(array $data)
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';
        return $this->incomeRepository->create($data);
    }

    public function update(string $encryptedId, array $data)
    {
        $income = $this->incomeRepository->findByEncryptedId($encryptedId);
        if (!$income) {
            throw new \Exception('Income not found');
        }
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        return $this->incomeRepository->update($income, $data);
    }

    public function delete(string $encryptedId)
    {
        $income = $this->incomeRepository->findByEncryptedId($encryptedId);
        if (!$income) {
            throw new \Exception('Income not found');
        }

        return $this->incomeRepository->softDelete($income);
    }
}


