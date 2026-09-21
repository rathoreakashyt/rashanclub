<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_activations', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key')->unique();
            $table->string('feature_name');
            $table->string('group')->default('general');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('company_id')->default(1);
            $table->timestamps();
            $table->index(['company_id', 'feature_key']);
        });

        // Seed default features matching sidebar sections
        $features = [
            ['feature_key' => 'pos', 'feature_name' => 'POS', 'group' => 'sale'],
            ['feature_key' => 'sale', 'feature_name' => 'Sale', 'group' => 'sale'],
            ['feature_key' => 'sale_return', 'feature_name' => 'Sale Return', 'group' => 'sale'],
            ['feature_key' => 'installment_sale', 'feature_name' => 'Installment Sale', 'group' => 'sale'],
            ['feature_key' => 'customer', 'feature_name' => 'Customer', 'group' => 'sale'],
            ['feature_key' => 'income', 'feature_name' => 'Income', 'group' => 'sale'],
            ['feature_key' => 'booking', 'feature_name' => 'Booking', 'group' => 'sale'],
            ['feature_key' => 'purchase', 'feature_name' => 'Purchase', 'group' => 'purchase'],
            ['feature_key' => 'purchase_return', 'feature_name' => 'Purchase Return', 'group' => 'purchase'],
            ['feature_key' => 'supplier', 'feature_name' => 'Supplier', 'group' => 'purchase'],
            ['feature_key' => 'expense', 'feature_name' => 'Expense', 'group' => 'purchase'],
            ['feature_key' => 'item', 'feature_name' => 'Item', 'group' => 'stock'],
            ['feature_key' => 'item_configuration', 'feature_name' => 'Item Configuration', 'group' => 'stock'],
            ['feature_key' => 'stock', 'feature_name' => 'Stock', 'group' => 'stock'],
            ['feature_key' => 'transfer', 'feature_name' => 'Transfer', 'group' => 'stock'],
            ['feature_key' => 'damage', 'feature_name' => 'Damage', 'group' => 'stock'],
            ['feature_key' => 'quotation', 'feature_name' => 'Quotation', 'group' => 'stock'],
            ['feature_key' => 'fixed_asset', 'feature_name' => 'Fixed Asset', 'group' => 'stock'],
            ['feature_key' => 'business_club', 'feature_name' => 'Business Club', 'group' => 'marketing'],
            ['feature_key' => 'price_list', 'feature_name' => 'Price Lists', 'group' => 'stock'],
            ['feature_key' => 'warranty_servicing', 'feature_name' => 'Warranty & Servicing', 'group' => 'sale'],
            ['feature_key' => 'accounting', 'feature_name' => 'Accounting', 'group' => 'accounting'],
            ['feature_key' => 'marketing', 'feature_name' => 'Marketing', 'group' => 'marketing'],
            ['feature_key' => 'hrm', 'feature_name' => 'Human Resource Management', 'group' => 'hrm'],
            ['feature_key' => 'report', 'feature_name' => 'Reports', 'group' => 'report'],
            ['feature_key' => 'gst', 'feature_name' => 'GST', 'group' => 'accounting'],
            ['feature_key' => 'inventory', 'feature_name' => 'Inventory', 'group' => 'stock'],
        ];

        $now = now();
        foreach ($features as $f) {
            DB::table('feature_activations')->insert(array_merge($f, [
                'is_active' => true,
                'company_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_activations');
    }
};
