<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('items', 'mrp_price')) {
            Schema::table('items', function (Blueprint $table) {
                $table->decimal('mrp_price', 15, 3)->default(0.000)->after('whole_sale_price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('mrp_price');
        });
    }
};
