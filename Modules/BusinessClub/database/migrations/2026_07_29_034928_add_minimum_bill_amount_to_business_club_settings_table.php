<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_club_settings', function (Blueprint $table) {
            $table->decimal('minimum_bill_amount', 15, 2)->default(0)->after('min_purchase_amount');
        });
    }

    public function down(): void
    {
        Schema::table('business_club_settings', function (Blueprint $table) {
            $table->dropColumn('minimum_bill_amount');
        });
    }
};
