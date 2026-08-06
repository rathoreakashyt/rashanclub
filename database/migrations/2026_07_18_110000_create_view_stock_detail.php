<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS view_stock_detail");

        Schema::create('view_stock_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable();
            $table->bigInteger('type')->default(0);
            $table->decimal('stock_quantity', 15, 3)->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('del_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('view_stock_detail');
    }
};
