<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\FeatureActivation;
use Illuminate\Http\Request;

class FeatureActivationController extends Controller
{
    public function index()
    {
        $companyId = session('company.company_id', 1);
        $features = FeatureActivation::where('company_id', $companyId)
            ->orderBy('group')
            ->orderBy('feature_name')
            ->get();

        $grouped = $features->groupBy('group');

        return view('backend.feature-activation.index', compact('features', 'grouped'));
    }

    public function update(Request $request)
    {
        $companyId = session('company.company_id', 1);

        $activeFeatures = $request->input('features', []);

        FeatureActivation::where('company_id', $companyId)->update(['is_active' => false]);

        if (!empty($activeFeatures)) {
            FeatureActivation::where('company_id', $companyId)
                ->whereIn('feature_key', $activeFeatures)
                ->update(['is_active' => true]);
        }

        FeatureActivation::clearCache($companyId);

        return redirect()->route('feature-activation.index')
            ->with('success', 'Feature activation updated successfully.');
    }
}
