<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix GST tax structure:
 * 1. Add missing CGST/SGST/IGST children for GST 5% and GST 12%
 * 2. Link IGST 18% as child of GST 18%
 * 3. Set outlet state_id (default to Maharashtra-27 if null)
 */
return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('taxs')->where('del_status', 'Live')->value('company_id') ?? 1;

        // Fix: IGST 18% should be child of GST 18% (id=6)
        DB::table('taxs')->where('id', 3)->update(['parent_tax_id' => 6]);

        // Add children for GST 5% (id=4) if not exist
        $gst5Children = DB::table('taxs')->where('parent_tax_id', 4)->count();
        if ($gst5Children === 0) {
            DB::table('taxs')->insert([
                ['tax_name' => 'CGST', 'tax_rate' => 2.50, 'parent_tax_id' => 4, 'show_in_item_profile' => 0, 'company_id' => $companyId, 'del_status' => 'Live', 'created_at' => now(), 'updated_at' => now()],
                ['tax_name' => 'SGST', 'tax_rate' => 2.50, 'parent_tax_id' => 4, 'show_in_item_profile' => 0, 'company_id' => $companyId, 'del_status' => 'Live', 'created_at' => now(), 'updated_at' => now()],
                ['tax_name' => 'IGST', 'tax_rate' => 5.00, 'parent_tax_id' => 4, 'show_in_item_profile' => 0, 'company_id' => $companyId, 'del_status' => 'Live', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Add children for GST 12% (id=5) if not exist
        $gst12Children = DB::table('taxs')->where('parent_tax_id', 5)->count();
        if ($gst12Children === 0) {
            DB::table('taxs')->insert([
                ['tax_name' => 'CGST', 'tax_rate' => 6.00, 'parent_tax_id' => 5, 'show_in_item_profile' => 0, 'company_id' => $companyId, 'del_status' => 'Live', 'created_at' => now(), 'updated_at' => now()],
                ['tax_name' => 'SGST', 'tax_rate' => 6.00, 'parent_tax_id' => 5, 'show_in_item_profile' => 0, 'company_id' => $companyId, 'del_status' => 'Live', 'created_at' => now(), 'updated_at' => now()],
                ['tax_name' => 'IGST', 'tax_rate' => 12.00, 'parent_tax_id' => 5, 'show_in_item_profile' => 0, 'company_id' => $companyId, 'del_status' => 'Live', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Fix: Set outlet state_id to Uttar Pradesh (id=9, code=09) if null
        // Change this to your actual state if different
        DB::table('outlets')->whereNull('state_id')->update(['state_id' => 9]);
    }

    public function down(): void
    {
        // Remove added children for GST 5% and 12%
        DB::table('taxs')->where('parent_tax_id', 4)->whereIn('tax_name', ['CGST', 'SGST', 'IGST'])->delete();
        DB::table('taxs')->where('parent_tax_id', 5)->whereIn('tax_name', ['CGST', 'SGST', 'IGST'])->delete();

        // Revert IGST parent
        DB::table('taxs')->where('id', 3)->update(['parent_tax_id' => null]);

        // Revert outlet state
        DB::table('outlets')->where('state_id', 9)->update(['state_id' => null]);
    }
};
