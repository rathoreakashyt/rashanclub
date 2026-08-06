<?php

namespace Modules\Configuration\Http\Controllers;

use Modules\Configuration\Http\Request\DeliveryPartnerRequest;
use Modules\Configuration\Services\DeliveryPartnerService;
use Illuminate\Routing\Controller;

class DeliveryPartnerController extends Controller
{
    /**
     * @var DeliveryPartnerService
     */
    protected $deliveryPartnerService;

    /**
     * DeliveryPartnerController constructor.
     *
     * @param DeliveryPartnerService $deliveryPartnerService
     */
    public function __construct(DeliveryPartnerService $deliveryPartnerService)
    {
        $this->deliveryPartnerService = $deliveryPartnerService;
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

            $data = $this->deliveryPartnerService->getDataTableData($params);
            return response()->json($data);
        }

        return view('configuration::delivery-partner.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('configuration::delivery-partner.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DeliveryPartnerRequest $request)
    {
        try {
            $this->deliveryPartnerService->createDeliveryPartner($request->validated());
            return redirect()->route('delivery-partner.index')
                ->with('success', 'Delivery Partner Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('delivery-partner.create')
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
            $partner = $this->deliveryPartnerService->getDeliveryPartnerByEncryptedId($id);
            
            if (!$partner) {
                return redirect()->route('delivery-partner.index')
                    ->with('error', 'Delivery Partner not found');
            }

            return view('configuration::delivery-partner.create', compact('partner'));
        } catch (\Exception $e) {
            return redirect()->route('delivery-partner.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DeliveryPartnerRequest $request, string $id)
    {
        try {
            $this->deliveryPartnerService->updateDeliveryPartner($id, $request->validated());
            return redirect()->route('delivery-partner.index')
                ->with('success', 'Delivery Partner Updated Successfully');
        } catch (\Exception $e) {
            return redirect()->route('delivery-partner.edit', $id)
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
            $this->deliveryPartnerService->deleteDeliveryPartner($id);
            return redirect()->route('delivery-partner.index')
                ->with('success', 'Delivery Partner Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('delivery-partner.index')
                ->with('error', $e->getMessage());
        }
    }
}

