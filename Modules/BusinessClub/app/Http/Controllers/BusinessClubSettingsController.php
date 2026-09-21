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
            'membership_amount' => 'required|numeric|min:0',
            'profit_share_percentage' => 'required|numeric|min:0|max:100',
            'redemption_day' => 'required|integer|min:1|max:31',
            'is_active' => 'nullable|boolean',
            'company_name' => 'nullable|string|max:255',
            'business_partner_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $data = $request->only([
            'membership_amount', 'profit_share_percentage', 'redemption_day',
            'is_active', 'company_name', 'business_partner_name',
            'address', 'phone', 'email', 'logo',
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('business-club/logos', 'public');
            $data['logo'] = $logoPath;
        }

        $data['is_active'] = $request->has('is_active') ? 1 : 0;

        $this->businessClubService->saveSettings($data, $companyId);

        return redirect()->back()->with('success', __('Business Club settings updated successfully'));
    }
}
