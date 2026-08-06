<?php

namespace Modules\Administrator\Services;

use Modules\Administrator\Models\EmployeeAdvancePayment;
use Modules\Administrator\Repositories\EmployeeAdvancePaymentRepository;
use App\Models\User;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeAdvancePaymentService
{
    protected $repository;

    public function __construct(EmployeeAdvancePaymentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all advance payments with pagination
     */
    public function getAllAdvancePayments($perPage = 10)
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Get DataTable data
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = '', ?string $dateFrom = null, ?string $dateTo = null, ?int $employeeId = null): array
    {
        $result = $this->repository->getDataTableData($length, $start, $search, $dateFrom, $dateTo, $employeeId);

        $transformedData = $result['data']->map(function ($item, $index) use ($start) {
            return [
                'id' => $start + $index + 1,
                'DT_RowIndex' => $start + $index + 1,
                'actual_id' => $item->id,
                'reference_no' => $item->reference_no,
                'date' => formatDate($item->date),
                'amount' => formatAmount($item->amount, 2),
                'employee_name' => $item->employee ? $item->employee->name : 'N/A',
                'payment_method' => $item->paymentMethod ? $item->paymentMethod->name : 'N/A',
                'note' => truncateText($item->note, 30, 10) ?? '',
                'encrypted_id' => $item->encrypted_id,
            ];
        });

        $totalAmount = $this->repository->getTotalAmount($dateFrom, $dateTo, $employeeId);

        return [
            'draw' => request()->draw ?? 1,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $transformedData,
            'totalAmount' => $totalAmount,
            'totalAmountFormatted' => formatAmount($totalAmount, 2),
        ];
    }

    /**
     * Get form data for create/edit
     */
    public function getFormData(?string $encryptedId = null): array
    {
        $data = [];

        $data['employees'] = User::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->name]);

        $data['paymentMethods'] = PaymentMethod::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('status', 'Enable')
            ->where('account_type', '!=', 'Loyalty Point')
            ->orderBy('sort_id')
            ->get(['id', 'name']);

        if ($encryptedId) {
            $data['advancePayment'] = $this->repository->findByEncryptedId($encryptedId);
            if (!$data['advancePayment']) {
                abort(404, 'Employee advance payment not found');
            }
        } else {
            $data['reference_no'] = $this->repository->generateReferenceNo();
        }

        return $data;
    }

    /**
     * Get repository instance
     */
    public function getRepository(): EmployeeAdvancePaymentRepository
    {
        return $this->repository;
    }

    /**
     * Create a new employee advance payment
     */
    public function createAdvancePayment($request): EmployeeAdvancePayment
    {
        DB::beginTransaction();
        try {
            $data = [
                'reference_no' => $request->input('reference_no'),
                'date' => $request->input('date'),
                'amount' => $request->input('amount'),
                'note' => $request->input('note'),
                'payment_method_id' => $request->input('payment_method_id'),
                'employee_id' => $request->input('employee_id'),
                'outlet_id' => session('outlet.outlet_id'),
                'user_id' => Auth::id(),
                'company_id' => session('company.company_id'),
                'del_status' => 'Live',
            ];
            $advancePayment = $this->repository->create($data);
            DB::commit();
            return $advancePayment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing employee advance payment
     */
    public function updateAdvancePayment(string $encryptedId, $request): EmployeeAdvancePayment
    {
        DB::beginTransaction();
        try {
            $advancePayment = $this->repository->findByEncryptedId($encryptedId);
            if (!$advancePayment) {
                throw new \Exception('Employee advance payment not found');
            }
            $data = [
                'reference_no' => $request->input('reference_no'),
                'date' => $request->input('date'),
                'amount' => $request->input('amount'),
                'note' => $request->input('note'),
                'payment_method_id' => $request->input('payment_method_id'),
                'employee_id' => $request->input('employee_id'),
                'outlet_id' => session('outlet.outlet_id'),
                'user_id' => Auth::id(),
            ];
            $this->repository->update($advancePayment, $data);
            DB::commit();
            return $advancePayment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete (soft) an employee advance payment
     */
    public function deleteAdvancePayment(string $encryptedId): bool
    {
        $advancePayment = $this->repository->findByEncryptedId($encryptedId);
        if (!$advancePayment) {
            throw new \Exception('Employee advance payment not found');
        }
        return $this->repository->delete($advancePayment);
    }

    /**
     * Get statistics
     */
    public function getAdvancePaymentStatistics(): array
    {
        $baseQuery = $this->repository->getModel()
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'));

        return [
            'total' => $this->repository->getTotalCount(),
            'total_amount' => (float) $baseQuery->sum('amount'),
            'today_count' => (clone $baseQuery)->whereDate('date', today())->count(),
            'today_amount' => (float) (clone $baseQuery)->whereDate('date', today())->sum('amount'),
            'this_month_count' => (clone $baseQuery)->whereMonth('date', now()->month)->whereYear('date', now()->year)->count(),
            'this_month_amount' => (float) (clone $baseQuery)->whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('amount'),
        ];
    }
}
