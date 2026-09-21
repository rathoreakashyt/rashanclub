<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Savings feature: MRP total vs bill amount.
     * - sales.mrp_total : MRP value of all items on the bill (sum of qty × item.mrp_price)
     * - sales.savings   : max(0, mrp_total - grand_total) — customer ka "bacha hua" amount
     * Backfill: purani sales ke liye bhi calculate (sale_details × items.mrp_price).
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('mrp_total', 15, 3)->default(0)->after('grand_total');
            $table->decimal('savings', 15, 3)->default(0)->after('mrp_total');
        });

        DB::statement("
            UPDATE sales s
            SET s.mrp_total = COALESCE((
                    SELECT SUM(sd.qty * i.mrp_price)
                    FROM sale_details sd
                    JOIN items i ON i.id = sd.item_id
                    WHERE sd.sales_id = s.id AND sd.del_status = 'Live'
                ), 0),
                s.savings = GREATEST(0, COALESCE((
                    SELECT SUM(sd.qty * i.mrp_price)
                    FROM sale_details sd
                    JOIN items i ON i.id = sd.item_id
                    WHERE sd.sales_id = s.id AND sd.del_status = 'Live'
                ), 0) - COALESCE(s.grand_total, 0))
            WHERE s.del_status = 'Live'
        ");
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['mrp_total', 'savings']);
        });
    }
};