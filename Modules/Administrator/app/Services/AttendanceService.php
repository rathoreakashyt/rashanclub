<?php

namespace Modules\Administrator\Services;

use Modules\Administrator\Models\Attendance;
use Modules\Administrator\Repositories\AttendanceRepository;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected $repository;

    public function __construct(AttendanceRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all attendances with pagination
     */
    public function getAllAttendances($perPage = 10)
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Get all attendances for DataTable
     */
    public function getDataTableData(int $length = 10, int $start = 0, string $search = '', ?string $dateFrom = null, ?string $dateTo = null, ?int $employeeId = null)
    {
        $result = $this->repository->getDataTableData($length, $start, $search, $dateFrom, $dateTo, $employeeId);
        
        $transformedAttendances = $result['data']->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'reference_no' => $attendance->reference_no,
                'date' => formatDate($attendance->date),
                'employee_name' => $attendance->employee ? ($attendance->employee->phone ? $attendance->employee->name . ' (' . $attendance->employee->phone . ')' : $attendance->employee->name) : 'N/A',
                'employee_email' => $attendance->employee ? $attendance->employee->email : 'N/A',
                'in_time' => $attendance->in_time ? formatDateTime($attendance->in_time) : 'N/A',
                'out_time' => $attendance->out_time ? formatDateTime($attendance->out_time) : 'N/A',
                'total_time' => ($attendance->in_time && $attendance->out_time)
                ? $attendance->in_time->diffInSeconds($attendance->out_time) / 3600 . 'h'
                : 'N/A',
                'note' => truncateText($attendance->note, 30, 10) ?? '',
                'encrypted_id' => $attendance->encrypted_id
            ];
        });

        // Calculate total hours for filtered records
        $totalHours = $this->repository->getTotalHours($dateFrom, $dateTo, $employeeId);
        $hours = floor($totalHours);
        $minutes = floor(($totalHours - $hours) * 60);

        return [
            'draw' => request()->draw ?? 1,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $transformedAttendances,
            'totalHours' => $totalHours,
            'totalHoursFormatted' => $hours . 'h ' . $minutes . 'm'
        ];
    }

    /**
     * Get data for create/edit form
     */
    public function getFormData(?string $encryptedId = null): array
    {
        $data = [];
        $data['employees'] = User::where('del_status', 'Live')
            ->where('company_id', session('company.company_id'))
            ->orderBy('name')
            ->pluck('name', 'id');
        
        if ($encryptedId) {
            $data['attendance'] = $this->repository->findByEncryptedId($encryptedId);
            if (!$data['attendance']) {
                abort(404, 'Attendance not found');
            }
        } else {
            // Generate reference number for new attendance
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
     * Create a new attendance
     */
    public function createAttendance(array $data): Attendance
    {
        DB::beginTransaction();
        try {
            // Prepare attendance data
            $attendanceData = [
                'reference_no' => $data['reference_no'],
                'date' => $data['date'],
                'employee_id' => $data['employee_id'],
                'in_time' => $data['in_time'],
                'out_time' => $data['out_time'] ?? null,
                'note' => $data['note'] ?? null,
                'company_id' => session('company.company_id'),
                'del_status' => 'Live',
                'user_id' => Auth::id(),
            ];

            // Create attendance
            $attendance = $this->repository->create($attendanceData);

            DB::commit();
            return $attendance;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing attendance
     */
    public function updateAttendance(string $encryptedId, array $data): Attendance
    {
        DB::beginTransaction();
        try {
            $attendance = $this->repository->findByEncryptedId($encryptedId);
            if (!$attendance) {
                throw new \Exception('Attendance not found');
            }
            $attendanceData = [
                'date' => $data['date'],
                'employee_id' => $data['employee_id'],
                'in_time' => $data['in_time'],
                'out_time' => $data['out_time'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => Auth::id(),
            ];
            $this->repository->update($attendance, $attendanceData);
            DB::commit();
            return $attendance->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete an attendance (soft delete)
     */
    public function deleteAttendance(string $encryptedId): bool
    {
        $attendance = $this->repository->findByEncryptedId($encryptedId);
        if (!$attendance) {
            throw new \Exception('Attendance not found');
        }

        return $this->repository->delete($attendance);
    }

    /**
     * Get attendances by date range
     */
    public function getAttendancesByDateRange(string $startDate, string $endDate)
    {
        return $this->repository->getByDateRange($startDate, $endDate);
    }

    /**
     * Get attendances by employee
     */
    public function getAttendancesByEmployee(int $employeeId)
    {
        return $this->repository->getByEmployee($employeeId);
    }

    /**
     * Get attendance statistics
     */
    public function getAttendanceStatistics()
    {
        return [
            'total' => $this->repository->getTotalCount(),
            'today' => $this->repository->getModel()
                ->where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->whereDate('date', today())
                ->count(),
            'this_week' => $this->repository->getModel()
                ->where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'this_month' => $this->repository->getModel()
                ->where('del_status', 'Live')
                ->where('company_id', session('company.company_id'))
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->count(),
        ];
    }
}
