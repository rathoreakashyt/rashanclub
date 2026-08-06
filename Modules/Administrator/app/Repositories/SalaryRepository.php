<?php

namespace Modules\Administrator\Repositories;

use Modules\Administrator\Models\Salary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalaryRepository
{
    protected $model;

    public function __construct(Salary $model)
    {
        $this->model = $model;
    }

    /**
     * Get all salaries
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod',
                'user'
            ])
            ->orderBy('generated_date', 'desc')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }

    /**
     * Get salaries with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod',
                'user'
            ])
            ->orderBy('generated_date', 'desc')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get salaries with pagination for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = ''): array
    {
        $query = $this->model->with([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod',
                'user'
            ])
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('generated_date', 'desc')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('year', 'like', "%{$search}%")
                  ->orWhere('month', 'like', "%{$search}%")
                  ->orWhere('generated_date', 'like', "%{$search}%")
                  ->orWhere('total_amount', 'like', "%{$search}%")
                  ->orWhereHas('salaryItems.employee', function($subQuery) use ($search) {
                      $subQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $recordsTotal = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();

        $recordsFiltered = $query->count();

        $data = $query->offset($start)->limit($length)->get();

        return [
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered
        ];
    }

    /**
     * Find salary by ID
     */
    public function find(int $id): ?Salary
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod',
                'user'
            ])
            ->find($id);
    }

    /**
     * Find salary by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Salary
    {
        try {
            $id = decrypt($encryptedId);
            // Ensure the decrypted value is an integer
            $id = (int) $id;
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new salary
     */
    public function create(array $data): Salary
    {
        return $this->model->create($data);
    }

    /**
     * Update a salary
     */
    public function update(Salary $salary, array $data): bool
    {
        return $salary->update($data);
    }

    /**
     * Delete a salary (soft delete)
     */
    public function delete(Salary $salary): bool
    {
        return $salary->update(['del_status' => 'Deleted']);
    }

    /**
     * Get total count of salaries
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Get salaries by year and month
     */
    public function getByYearAndMonth(int $year, int $month): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('year', $year)
            ->where('month', $month)
            ->with([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod',
                'user'
            ])
            ->orderBy('generated_date', 'desc')
            ->get();
    }

    /**
     * Get the model instance
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Generate unique reference number
     */
    public function generateReferenceNo(): string
    {
        $count = $this->model->where('company_id', session('company.company_id'))->count();
        return 'SAL-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }
}

