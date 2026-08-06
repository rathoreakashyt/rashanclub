<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restores the view_stock_detail2 view referenced by the Expire Soon Report
 * (Modules/Report/app/Http/Controllers/ReportController.php -> expireSoonReport()).
 *
 * The table did not exist in the off_pos database, so the report's AJAX data
 * call failed with "Table 'off_pos.view_stock_detail2' doesn't exist" as soon
 * as any Medicine_Product item was present. The view exposes per-purchase-batch
 * expiry stock derived from purchase_details so the report can segment stock by
 * expiry date.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW view_stock_detail2 AS
            SELECT
                pd.item_id,
                pd.outlet_id,
                pd.company_id,
                pd.del_status,
                pd.expiry_imei_serial,
                SUM(pd.quantity_amount) AS stock_quantity
            FROM purchase_details pd
            WHERE pd.expiry_imei_serial IS NOT NULL
              AND pd.expiry_imei_serial != ''
            GROUP BY
                pd.item_id,
                pd.outlet_id,
                pd.company_id,
                pd.del_status,
                pd.expiry_imei_serial
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS view_stock_detail2');
    }
};
