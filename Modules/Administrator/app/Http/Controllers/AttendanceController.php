<?php

namespace Modules\Administrator\Http\Controllers;

use Modules\Administrator\Http\Request\AttendanceRequest;
use Modules\Administrator\Services\AttendanceService;
use App\Http\Controllers\Controller;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $dateFrom = request()->date_from ?? null;
            $dateTo = request()->date_to ?? null;
            $employeeId = request()->employee_id ? (int)request()->employee_id : null;
            
            $data = $this->attendanceService->getDataTableData($length, $start, $search, $dateFrom, $dateTo, $employeeId);
            
            return response()->json($data);
        }
        
        $statistics = $this->attendanceService->getAttendanceStatistics();
        return view('administrator::attendance.index', compact('statistics'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->attendanceService->getFormData();
        return view('administrator::attendance.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttendanceRequest $request)
    {
        try {
            $attendanceData = $request->all();
            $this->attendanceService->createAttendance($attendanceData);
            return redirect()->route('attendance.index')
                ->with('success', 'Attendance Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('attendance.create')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = $this->attendanceService->getFormData($id);
        return view('administrator::attendance.create', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttendanceRequest $request, string $id)
    {
        try {
            $attendance = $this->attendanceService->getRepository()->findByEncryptedId($id);
            if (!$attendance) {
                return redirect()->route('attendance.index')
                    ->with('error', 'Attendance not found');
            }
        } catch (\Exception $e) {
            return redirect()->route('attendance.index')
                ->with('error', 'Invalid attendance ID');
        }

        try {
            $attendanceData = $request->all();
            $this->attendanceService->updateAttendance($id, $attendanceData);
            return redirect()->route('attendance.edit', $id)
                ->with('success', 'Attendance Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('attendance.edit', $id)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->attendanceService->deleteAttendance($id);
            return redirect()->route('attendance.index')
                ->with('success', 'Attendance Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('attendance.index')
                ->with('error', $e->getMessage());
        }
    }
}
