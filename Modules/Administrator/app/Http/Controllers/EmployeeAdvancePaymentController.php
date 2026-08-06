<?php

namespace Modules\Administrator\Http\Controllers;

use Modules\Administrator\Http\Request\EmployeeAdvancePaymentRequest;
use Modules\Administrator\Services\EmployeeAdvancePaymentService;
use App\Http\Controllers\Controller;

class EmployeeAdvancePaymentController extends Controller
{
    protected $advancePaymentService;

    public function __construct(EmployeeAdvancePaymentService $advancePaymentService)
    {
        $this->advancePaymentService = $advancePaymentService;
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
            $employeeId = request()->employee_id ? (int) request()->employee_id : null;

            $data = $this->advancePaymentService->getDataTableData($length, $start, $search, $dateFrom, $dateTo, $employeeId);
            return response()->json($data);
        }

        $statistics = $this->advancePaymentService->getAdvancePaymentStatistics();
        return view('administrator::employee_advance_payment.index', compact('statistics'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->advancePaymentService->getFormData();
        return view('administrator::employee_advance_payment.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeAdvancePaymentRequest $request)
    {
        try {
            $this->advancePaymentService->createAdvancePayment($request);
            return redirect()->route('employee-advance-payment.index')
                ->with('success', __('Employee advance payment created successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('employee-advance-payment.create')
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
            $advancePayment = $this->advancePaymentService->getRepository()->findByEncryptedId($id);
            if (!$advancePayment) {
                return redirect()->route('employee-advance-payment.index')
                    ->with('error', __('Employee advance payment not found.'));
            }
            return view('administrator::employee_advance_payment.show', compact('advancePayment'));
        } catch (\Exception $e) {
            return redirect()->route('employee-advance-payment.index')
                ->with('error', __('Invalid advance payment ID.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $data = $this->advancePaymentService->getFormData($id);
            return view('administrator::employee_advance_payment.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('employee-advance-payment.index')
                ->with('error', __('Invalid advance payment ID.'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeAdvancePaymentRequest $request, string $id)
    {
        try {
            $this->advancePaymentService->updateAdvancePayment($id, $request);
            return redirect()->route('employee-advance-payment.index')
                ->with('success', __('Employee advance payment updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('employee-advance-payment.edit', $id)
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
            $this->advancePaymentService->deleteAdvancePayment($id);
            return redirect()->route('employee-advance-payment.index')
                ->with('success', __('Employee advance payment deleted successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('employee-advance-payment.index')
                ->with('error', $e->getMessage());
        }
    }
}
