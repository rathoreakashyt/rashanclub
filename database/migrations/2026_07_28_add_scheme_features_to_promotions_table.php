<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'start_time')) {
                $table->time('start_time')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('promotions', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('promotions', 'applicable_customers')) {
                $table->json('applicable_customers')->nullable()->after('applicable_categories');
            }
            if (!Schema::hasColumn('promotions', 'applicable_customer_types')) {
                $table->json('applicable_customer_types')->nullable()->after('applicable_customers');
            }
            if (!Schema::hasColumn('promotions', 'scheme_basis')) {
                $table->string('scheme_basis', 50)->default('item')->after('type');
            }
            if (!Schema::hasColumn('promotions', 'bill_level_discount')) {
                $table->decimal('bill_level_discount', 15, 3)->default(0.000)->after('max_discount_amount');
            }
            if (!Schema::hasColumn('promotions', 'bill_level_discount_type')) {
                $table->string('bill_level_discount_type', 20)->nullable()->after('bill_level_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'start_time', 'end_time', 'applicable_customers',
                'applicable_customer_types', 'scheme_basis',
                'bill_level_discount', 'bill_level_discount_type'
            ]);
        });
    }
};
