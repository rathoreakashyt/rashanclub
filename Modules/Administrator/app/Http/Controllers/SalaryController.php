<?php

namespace Modules\Administrator\Http\Controllers;

use Modules\Administrator\Http\Request\SalaryRequest;
use Modules\Administrator\Services\SalaryService;
use Modules\Administrator\Repositories\EmployeeAdvancePaymentRepository;
use App\Http\Controllers\Controller;

class SalaryController extends Controller
{
    protected $salaryService;

    protected $advancePaymentRepository;

    public function __construct(SalaryService $salaryService, EmployeeAdvancePaymentRepository $advancePaymentRepository)
    {
        $this->salaryService = $salaryService;
        $this->advancePaymentRepository = $advancePaymentRepository;
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
            
            $data = $this->salaryService->getDataTableData($length, $start, $search);
            
            return response()->json($data);
        }
        
        $statistics = $this->salaryService->getSalaryStatistics();
        return view('administrator::salary.index', compact('statistics'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->salaryService->getFormData();
        return view('administrator::salary.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SalaryRequest $request)
    {
        try {
            $this->salaryService->createSalary($request);
            return redirect()->route('salary.index')
                ->with('success', 'Salary Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('salary.create')
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $salary = $this->salaryService->getRepository()->findByEncryptedId($id);
            if (!$salary) {
                return redirect()->route('salary.index')
                    ->with('error', 'Salary not found');
            }
            return view('administrator::salary.show', compact('salary'));
        } catch (\Exception $e) {
            return redirect()->route('salary.index')
                ->with('error', 'Invalid salary ID');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $data = $this->salaryService->getFormData($id);
            return view('administrator::salary.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('salary.index')
                ->with('error', 'Invalid salary ID');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SalaryRequest $request, string $id)
    {
        try {
            $this->salaryService->updateSalary($id, $request);
            return redirect()->route('salary.index', $id)
                ->with('success', 'Salary Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('salary.edit', $id)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->salaryService->deleteSalary($id);
            return redirect()->route('salary.index')
                ->with('success', 'Salary Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('salary.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get total advance taken per employee for a given year and month (for salary form).
     */
    public function advancesByMonth()
    {
        $year = (int) request()->input('year', date('Y'));
        $month = (int) request()->input('month', date('n'));
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return response()->json(['advances' => []]);
        }
        $advances = $this->advancePaymentRepository->getAdvanceTotalsByMonth($year, $month);
        return response()->json(['advances' => $advances]);
    }
}

