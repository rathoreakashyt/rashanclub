<?php

namespace Modules\Administrator\Repositories;

use Modules\Administrator\Models\EmployeeAdvancePayment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeAdvancePaymentRepository
{
    protected $model;

    public function __construct(EmployeeAdvancePayment $model)
    {
        $this->model = $model;
    }

    /**
     * Get all employee advance payments
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['employee', 'user', 'paymentMethod'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get advance payments with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['employee', 'user', 'paymentMethod'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get advance payments for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = '', ?string $dateFrom = null, ?string $dateTo = null, ?int $employeeId = null): array
    {
        $query = $this->model->with(['employee', 'user', 'paymentMethod'])
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('paymentMethod', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%{$search}%");
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
            'recordsFiltered' => $recordsFiltered,
        ];
    }

    /**
     * Find advance payment by ID
     */
    public function find(int $id): ?EmployeeAdvancePayment
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['employee', 'user', 'paymentMethod'])
            ->find($id);
    }

    /**
     * Find advance payment by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?EmployeeAdvancePayment
    {
        try {
            $id = (int) decrypt($encryptedId);
            return $this->find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a new advance payment
     */
    public function create(array $data): EmployeeAdvancePayment
    {
        return $this->model->create($data);
    }

    /**
     * Update an advance payment
     */
    public function update(EmployeeAdvancePayment $advancePayment, array $data): bool
    {
        return $advancePayment->update($data);
    }

    /**
     * Soft delete an advance payment
     */
    public function delete(EmployeeAdvancePayment $advancePayment): bool
    {
        return $advancePayment->update(['del_status' => 'Deleted']);
    }

    /**
     * Get total count
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Get total advance amount taken by an employee in a given year and month
     */
    public function getTotalAdvanceByEmployeeAndMonth(int $employeeId, int $year, int $month): float
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $lastDay = (int) date('t', strtotime($startDate));
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);

        return (float) $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Get total advance amounts by employee for a given year and month (returns employee_id => total)
     */
    public function getAdvanceTotalsByMonth(int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $lastDay = (int) date('t', strtotime($startDate));
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);

        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('employee_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /**
     * Get total amount for filtered records
     */
    public function getTotalAmount(?string $dateFrom = null, ?string $dateTo = null, ?int $employeeId = null): float
    {
        $query = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'));

        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return (float) $query->sum('amount');
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
        return 'EAP-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }
}
