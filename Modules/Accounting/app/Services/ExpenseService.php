<?php

namespace Modules\Accounting\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Repositories\ExpenseRepository;
use Modules\Accounting\Repositories\ExpenseCategoryRepository;
use Modules\Accounting\Repositories\PaymentMethodRepository;

class ExpenseService
{
    public function __construct(
        private ExpenseRepository $expenseRepository,
        private ExpenseCategoryRepository $expenseCategoryRepository,
        private PaymentMethodRepository $paymentMethodRepository
    ) {
    }

    /**
     * Prepare datatable payload for expenses.
     */
    public function getDataTable(int $length, int $start, ?string $search): array
    {
        $page = $length > 0 ? (int) floor($start / $length) + 1 : 1;
        $paginator = $this->expenseRepository->paginateWithFilters($length, $page, $search);

        $recordsTotal = $this->expenseRepository->countLive();
        $filteredCount = $paginator->total();
        $startingNumber = $filteredCount - $start;

        $data = collect($paginator->items())->map(function ($expense, $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $expense->id,
                'reference_no' => $expense->reference_no,
                'date' => formatDate($expense->date),
                'category_id' => $expense->category?->name ?? '',
                'amount' => formatAmount($expense->amount),
                'account' => $expense->account?->name ?? '',
                'employee_id' => $expense->employee?->name ?? '',
                'note' => truncateText($expense->note, 30, 10),
                'encrypted_id' => $expense->encrypted_id,
            ];
        })->values();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ];
    }

    /**
     * Get data needed to render the create form.
     */
    public function getCreateData(): array
    {
        $lastRecord = $this->expenseRepository->getLastForCompany();
        $nextId = $lastRecord ? $lastRecord->id + 1 : 1;

        return [
            'reference_no' => str_pad($nextId, 6, '0', STR_PAD_LEFT),
            'expense_categories' => $this->expenseCategoryRepository->listForSelect(),
            'employees' => User::where('del_status', 'Live')->select('id', 'name')->orderBy('name')->get(),
            'payment_methods' => $this->paymentMethodRepository->listForSelect(),
        ];
    }

    /**
     * Get data needed to render the edit form.
     */
    public function getEditData(string $encryptedId): array
    {
        $expense = $this->expenseRepository->findByEncryptedId($encryptedId);
        if (!$expense) {
            throw new \Exception('Expense not found');
        }

        return array_merge($this->getCreateData(), [
            'expense' => $expense,
            'reference_no' => $expense->reference_no,
        ]);
    }

    /**
     * Create a new expense.
     */
    public function create(array $data)
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';

        return $this->expenseRepository->create($data);
    }

    /**
     * Update an existing expense.
     */
    public function update(string $encryptedId, array $data)
    {
        $expense = $this->expenseRepository->findByEncryptedId($encryptedId);
        if (!$expense) {
            throw new \Exception('Expense not found');
        }

        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');

        return $this->expenseRepository->update($expense, $data);
    }

    /**
     * Soft delete an expense.
     */
    public function delete(string $encryptedId)
    {
        $expense = $this->expenseRepository->findByEncryptedId($encryptedId);
        if (!$expense) {
            throw new \Exception('Expense not found');
        }

        return $this->expenseRepository->softDelete($expense);
    }
}


