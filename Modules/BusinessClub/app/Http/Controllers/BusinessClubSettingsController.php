<?php

namespace Modules\BusinessClub\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\BusinessClub\Services\BusinessClubService;

class BusinessClubSettingsController extends Controller
{
    protected $businessClubService;

    public function __construct(BusinessClubService $businessClubService)
    {
        $this->businessClubService = $businessClubService;
    }

    public function index()
    {
        $companyId = session('company.company_id');
        $settings = $this->businessClubService->getSettings($companyId);
        return view('businessclub::settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $companyId = session('company.company_id');

        $request->validate([
            'profit_percentage' => 'required|numeric|min:0|max:100',
            'redemption_date' => 'required|integer|min:1|max:31',
            'min_purchase_amount' => 'required|numeric|min:0',
            'minimum_bill_amount' => 'required|numeric|min:0',
        ]);

        $this->businessClubService->saveSettings($request->all(), $companyId);

        return redirect()->back()->with('success', __('Business Club settings updated successfully'));
    }
}
