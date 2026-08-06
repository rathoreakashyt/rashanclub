<?php

namespace Modules\Configuration\Http\Controllers;

use Modules\Configuration\Http\Request\PrinterRequest;
use Modules\Configuration\Services\PrinterService;
use Illuminate\Routing\Controller;

class PrinterController extends Controller
{
    /**
     * @var PrinterService
     */
    protected $printerService;

    /**
     * PrinterController constructor.
     *
     * @param PrinterService $printerService
     */
    public function __construct(PrinterService $printerService)
    {
        $this->printerService = $printerService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $params = [
                'draw' => request()->draw ?? 1,
                'length' => request()->length ?? 10,
                'start' => request()->start ?? 0,
                'search' => request()->search['value'] ?? '',
            ];

            $data = $this->printerService->getDataTableData($params);
            return response()->json($data);
        }

        return view('configuration::printer.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('configuration::printer.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PrinterRequest $request)
    {
        try {
            $printer = $this->printerService->createPrinter($request->validated());

            if ($request->ajax()) {
                $printers = $this->printerService->listForSelect();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Printer Created Successfully',
                    'data' => [
                        'printer' => [
                            'id' => $printer->id,
                            'title' => $printer->title,
                        ],
                        'printers' => $printers,
                    ],
                ], 201);
            }

            return redirect()->route('printer.index')
                ->with('success', 'Printer Created Successfully');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 500);
            }
            return redirect()->route('printer.create')
                ->with('error', $e->getMessage())
                ->withInput();
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
        try {
            $printer = $this->printerService->getPrinterByEncryptedId($id);
            
            if (!$printer) {
                return redirect()->route('printer.index')
                    ->with('error', 'Printer not found');
            }

            return view('configuration::printer.create', compact('printer'));
        } catch (\Exception $e) {
            return redirect()->route('printer.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PrinterRequest $request, string $id)
    {
        try {
            $this->printerService->updatePrinter($id, $request->validated());
            return redirect()->route('printer.index')
                ->with('success', 'Printer Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('printer.edit', $id)
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
            $this->printerService->deletePrinter($id);
            return redirect()->route('printer.index')
                ->with('success', 'Printer Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('printer.index')
                ->with('error', $e->getMessage());
        }
    }
}

