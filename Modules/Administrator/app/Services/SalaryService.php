<?php

namespace Modules\Administrator\Services;

use Modules\Administrator\Models\Salary;
use Modules\Administrator\Repositories\SalaryRepository;
use Modules\Administrator\Http\Request\SalaryRequest;
use App\Models\User;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    protected $repository;

    public function __construct(SalaryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all salaries with pagination
     */
    public function getAllSalaries($perPage = 10)
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Get all salaries for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = '')
    {
        $result = $this->repository->getDataTableData($length, $start, $search);
        
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $transformedSalaries = $result['data']->map(function ($salary, $index) use ($monthNames, $start) {
            return [
                'id' => $start + $index + 1,
                'DT_RowIndex' => $start + $index + 1,
                'reference_no' => $salary->reference_no,
                'year' => $salary->year,
                'month' => $monthNames[$salary->month] ?? $salary->month,
                'generated_date' => formatDate($salary->generated_date),
                'total_amount' => formatAmount($salary->total_amount, 2),
                'employee_count' => $salary->salaryItems->count(),
                'encrypted_id' => $salary->encrypted_id,
                'actual_id' => $salary->id,
            ];
        });

        return [
            'draw' => request()->draw ?? 1,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $transformedSalaries
        ];
    }

    /**
     * Get data for create/edit form
     */
    public function getFormData(?string $encryptedId = null): array
    {
        $data = [];
        
        // Get employees
        $data['employees'] = User::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('salary', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'salary'])
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'salary' => $employee->salary
                ];
            });

        
        // Get payment methods
        $data['paymentMethods'] = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', 'Enable')
            ->where('account_type', '!=', 'Loyalty Point')
            ->orderBy('sort_id')
            ->get(['id', 'name']);
        
        if ($encryptedId) {
            $data['salary'] = $this->repository->findByEncryptedId($encryptedId);
            if (!$data['salary']) {
                abort(404, 'Salary not found');
            }
        } else {
            // Generate reference number for new salary
            $data['reference_no'] = $this->repository->generateReferenceNo();
        }

        return $data;
    }

    /**
     * Get repository instance (for controller access)
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Calculate net salary for an item
     */
    public function calculateNetSalary(array $item): float
    {
        $salaryAmount = floatval($item['salary_amount'] ?? 0);
        $overtimeRate = floatval($item['overtime_rate'] ?? 0);
        $overtimeHour = floatval($item['overtime_hour'] ?? 0);
        $additionalAmount = floatval($item['additional_amount'] ?? 0);
        $deductionAmount = floatval($item['deduction_amount'] ?? 0);
        $absentDay = intval($item['absent_day'] ?? 0);
        $absentDayAmount = floatval($item['absent_day_amount'] ?? 0);
        $advanceTaken = floatval($item['advance_taken'] ?? 0);

        // Calculate overtime amount
        $overtimeAmount = $overtimeRate * $overtimeHour;
        
        // Calculate absent deduction
        $absentDeduction = $absentDay * $absentDayAmount;

        // Net salary = base salary + overtime + additional - deductions - absent - advance
        $netSalary = $salaryAmount 
            + $overtimeAmount 
            + $additionalAmount 
            - $deductionAmount 
            - $absentDeduction 
            - $advanceTaken;

        return max(0, $netSalary); // Ensure non-negative
    }

    /**
     * Create a new salary
     */
    public function createSalary(SalaryRequest $request): Salary
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            
            // Calculate total amount from items
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $item['net_salary'] = $this->calculateNetSalary($item);
                $totalAmount += $item['net_salary'];
            }
            
            // Prepare salary data
            $salaryData = [
                'reference_no' => $data['reference_no'],
                'year' => $data['year'],
                'month' => $data['month'],
                'generated_date' => $data['generated_date'],
                'total_amount' => $totalAmount,
                'company_id' => session('company.company_id'),
                'del_status' => 'Live',
                'user_id' => Auth::id(),
            ];

            // Create salary
            $salary = $this->repository->create($salaryData);

            // Create salary items
            foreach ($data['items'] as $item) {
                $salary->salaryItems()->create([
                    'employee_id' => $item['employee_id'],
                    'salary_amount' => $item['salary_amount'],
                    'overtime_rate' => $item['overtime_rate'] ?? 0,
                    'overtime_hour' => $item['overtime_hour'] ?? 0,
                    'additional_amount' => $item['additional_amount'] ?? 0,
                    'deduction_amount' => $item['deduction_amount'] ?? 0,
                    'absent_day' => $item['absent_day'] ?? 0,
                    'absent_day_amount' => $item['absent_day_amount'] ?? 0,
                    'tips' => 0,
                    'advance_taken' => $item['advance_taken'] ?? 0,
                    'net_salary' => $item['net_salary'],
                    'note' => $item['note'] ?? null,
                    'company_id' => session('company.company_id'),
                    'del_status' => 'Live',
                    'user_id' => Auth::id(),
                ]);
            }

            // Create salary payments
            foreach ($data['payments'] as $payment) {
                $salary->salaryPayments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'company_id' => session('company.company_id'),
                    'del_status' => 'Live',
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();
            return $salary->fresh([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing salary
     */
    public function updateSalary(string $encryptedId, SalaryRequest $request): Salary
    {
        DB::beginTransaction();
        try {
            $salary = $this->repository->findByEncryptedId($encryptedId);
            if (!$salary) {
                throw new \Exception('Salary not found');
            }

            $data = $request->validated();
            
            // Calculate total amount from items
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $item['net_salary'] = $this->calculateNetSalary($item);
                $totalAmount += $item['net_salary'];
            }
            
            $salaryData = [
                'reference_no' => $data['reference_no'],
                'year' => $data['year'],
                'month' => $data['month'],
                'generated_date' => $data['generated_date'],
                'total_amount' => $totalAmount,
                'user_id' => Auth::id(),
            ];
            
            $this->repository->update($salary, $salaryData);

            // Delete existing items and payments (hard delete to prevent duplicates)
            $salary->salaryItems()->where('del_status', 'Live')->delete();
            $salary->salaryPayments()->where('del_status', 'Live')->delete();

            // Create new salary items
            foreach ($data['items'] as $item) {
                $salary->salaryItems()->create([
                    'employee_id' => $item['employee_id'],
                    'salary_amount' => $item['salary_amount'],
                    'overtime_rate' => $item['overtime_rate'] ?? 0,
                    'overtime_hour' => $item['overtime_hour'] ?? 0,
                    'additional_amount' => $item['additional_amount'] ?? 0,
                    'deduction_amount' => $item['deduction_amount'] ?? 0,
                    'absent_day' => $item['absent_day'] ?? 0,
                    'absent_day_amount' => $item['absent_day_amount'] ?? 0,
                    'tips' => 0,
                    'advance_taken' => $item['advance_taken'] ?? 0,
                    'net_salary' => $item['net_salary'],
                    'note' => $item['note'] ?? null,
                    'company_id' => session('company.company_id'),
                    'del_status' => 'Live',
                    'user_id' => Auth::id(),
                ]);
            }

            // Create new salary payments
            foreach ($data['payments'] as $payment) {
                $salary->salaryPayments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'company_id' => session('company.company_id'),
                    'del_status' => 'Live',
                    'user_id' => Auth::id(),
                ]);
            }

            DB::commit();
            return $salary->fresh([
                'salaryItems' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryItems.employee',
                'salaryPayments' => function($query) {
                    $query->where('del_status', 'Live');
                },
                'salaryPayments.paymentMethod'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a salary (soft delete)
     */
    public function deleteSalary(string $encryptedId): bool
    {
        $salary = $this->repository->findByEncryptedId($encryptedId);
        if (!$salary) {
            throw new \Exception('Salary not found');
        }

        return $this->repository->delete($salary);
    }

    /**
     * Get salaries by year and month
     */
    public function getSalariesByYearAndMonth(int $year, int $month)
    {
        return $this->repository->getByYearAndMonth($year, $month);
    }

    /**
     * Get salary statistics
     */
    public function getSalaryStatistics()
    {
        return [
            'total' => $this->repository->getTotalCount(),
            'this_month' => $this->repository->getModel()
                ->where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->whereMonth('generated_date', now()->month)
                ->whereYear('generated_date', now()->year)
                ->count(),
            'this_year' => $this->repository->getModel()
                ->where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->whereYear('generated_date', now()->year)
                ->count(),
        ];
    }
}

