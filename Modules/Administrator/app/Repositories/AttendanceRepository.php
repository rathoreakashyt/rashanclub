<?php

namespace Modules\Administrator\Repositories;

use Modules\Administrator\Models\Attendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceRepository
{
    protected $model;

    public function __construct(Attendance $model)
    {
        $this->model = $model;
    }

    /**
     * Get all attendances
     */
    public function all(): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['employee'])
            ->orderBy('date', 'desc')
            ->orderBy('in_time', 'desc')
            ->get();
    }

    /**
     * Get attendances with pagination
     */
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['employee'])
            ->orderBy('date', 'desc')
            ->orderBy('in_time', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get attendances with pagination for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = '', ?string $dateFrom = null, ?string $dateTo = null, ?int $employeeId = null): array
    {
        $query = $this->model->with(['employee'])
            ->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('date', 'desc')
            ->orderBy('in_time', 'desc');

        // Apply date filters
        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        // Apply employee filter
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhereHas('employee', function($subQuery) use ($search) {
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
     * Find attendance by ID
     */
    public function find(int $id): ?Attendance
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->with(['employee'])
            ->find($id);
    }

    /**
     * Find attendance by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Attendance
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
     * Create a new attendance
     */
    public function create(array $data): Attendance
    {
        return $this->model->create($data);
    }

    /**
     * Update an attendance
     */
    public function update(Attendance $attendance, array $data): bool
    {
        return $attendance->update($data);
    }

    /**
     * Delete an attendance (soft delete)
     */
    public function delete(Attendance $attendance): bool
    {
        return $attendance->update(['del_status' => 'Deleted']);
    }

    /**
     * Get total count of attendances
     */
    public function getTotalCount(): int
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->count();
    }

    /**
     * Get attendances by date range
     */
    public function getByDateRange(string $startDate, string $endDate): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['employee'])
            ->orderBy('date', 'desc')
            ->orderBy('in_time', 'desc')
            ->get();
    }

    /**
     * Get attendances by employee
     */
    public function getByEmployee(int $employeeId): Collection
    {
        return $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->where('employee_id', $employeeId)
            ->with(['employee'])
            ->orderBy('date', 'desc')
            ->orderBy('in_time', 'desc')
            ->get();
    }

    /**
     * Get total hours for filtered attendances
     */
    public function getTotalHours(?string $dateFrom = null, ?string $dateTo = null, ?int $employeeId = null): float
    {
        $query = $this->model->where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->whereNotNull('in_time')
            ->whereNotNull('out_time');

        // Apply date filters
        if ($dateFrom) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('date', '<=', $dateTo);
        }

        // Apply employee filter
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $attendances = $query->get();
        
        $totalMinutes = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->in_time && $attendance->out_time) {
                $inTime = \Carbon\Carbon::parse($attendance->in_time);
                $outTime = \Carbon\Carbon::parse($attendance->out_time);
                
                if ($outTime->greaterThan($inTime)) {
                    $totalMinutes += $outTime->diffInMinutes($inTime);
                }
            }
        }
        
        return $totalMinutes / 60; // Convert to hours
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
        $count = $this->model->count();
        return str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }
}
