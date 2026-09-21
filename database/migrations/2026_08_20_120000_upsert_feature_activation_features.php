<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = config('feature_activation.features', []);
        $companyId = 1;
        $now = now();

        // Legacy keys jo purane seed me the par config me na hon — unhe bhi rakhen (backward compat)
        $legacy = [
            'sale' => ['name' => 'Sale', 'group' => 'Sale & Customer'],
            'item' => ['name' => 'Item', 'group' => 'Item & Stock'],
            'item_configuration' => ['name' => 'Item Configuration', 'group' => 'Item & Stock'],
            'stock' => ['name' => 'Stock', 'group' => 'Item & Stock'],
            'inventory' => ['name' => 'Inventory', 'group' => 'Item & Stock'],
            'sale_return' => ['name' => 'Sale Return', 'group' => 'Sale & Customer'],
            'installment_sale' => ['name' => 'Installment Sale', 'group' => 'Sale & Customer'],
            'customer' => ['name' => 'Customer', 'group' => 'Sale & Customer'],
            'income' => ['name' => 'Income', 'group' => 'Sale & Customer'],
            'purchase' => ['name' => 'Purchase', 'group' => 'Purchase & Supplier'],
            'purchase_return' => ['name' => 'Purchase Return', 'group' => 'Purchase & Supplier'],
            'supplier' => ['name' => 'Supplier', 'group' => 'Purchase & Supplier'],
            'expense' => ['name' => 'Expense', 'group' => 'Purchase & Supplier'],
            'transfer' => ['name' => 'Transfer', 'group' => 'Transfer & Damage'],
            'damage' => ['name' => 'Damage', 'group' => 'Transfer & Damage'],
            'quotation' => ['name' => 'Quotation', 'group' => 'Transfer & Damage'],
            'fixed_asset' => ['name' => 'Fixed Asset', 'group' => 'Fixed Asset'],
            'business_club' => ['name' => 'Business Club', 'group' => 'Business Club'],
            'price_list' => ['name' => 'Price Lists', 'group' => 'Price Lists'],
            'warranty_servicing' => ['name' => 'Warranty & Servicing', 'group' => 'Warranty & Servicing'],
            'accounting' => ['name' => 'Accounting', 'group' => 'Accounting'],
            'marketing' => ['name' => 'Marketing', 'group' => 'Marketing'],
            'hrm' => ['name' => 'Human Resource Management', 'group' => 'Human Resource'],
            'report' => ['name' => 'Reports', 'group' => 'Report & Setting'],
            'gst' => ['name' => 'GST', 'group' => 'Accounting'],
            'settings' => ['name' => 'Settings', 'group' => 'Report & Setting'],
            'pos' => ['name' => 'POS', 'group' => 'Sale & Customer'],
            'booking' => ['name' => 'Booking', 'group' => 'Main'],
        ];

        foreach ($features as $f) {
            $key = $f['key'];
            $exists = DB::table('feature_activations')
                ->where('company_id', $companyId)
                ->where('feature_key', $key)
                ->exists();

            if ($exists) {
                DB::table('feature_activations')
                    ->where('company_id', $companyId)
                    ->where('feature_key', $key)
                    ->update([
                        'feature_name' => $f['name'],
                        'group' => $f['group'],
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('feature_activations')->insert([
                    'feature_key' => $key,
                    'feature_name' => $f['name'],
                    'group' => $f['group'],
                    'is_active' => true,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($legacy as $key => $meta) {
            $exists = DB::table('feature_activations')
                ->where('company_id', $companyId)
                ->where('feature_key', $key)
                ->exists();
            if (!$exists) {
                DB::table('feature_activations')->insert([
                    'feature_key' => $key,
                    'feature_name' => $meta['name'],
                    'group' => $meta['group'],
                    'is_active' => true,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        \App\Models\FeatureActivation::clearCache($companyId);
    }

    public function down(): void
    {
        // data-preserving: kuch nahi delete karte
    }
};