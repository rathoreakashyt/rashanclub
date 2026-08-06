<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('price_lists')) {
            Schema::create('price_lists', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('description', 500)->nullable();
                $table->string('customer_type', 20)->nullable()->comment('retail/wholesale/all');
                $table->bigInteger('user_id')->unsigned();
                $table->bigInteger('outlet_id')->unsigned()->nullable();
                $table->bigInteger('company_id')->unsigned();
                $table->string('del_status', 20)->default('Live');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('price_list_items')) {
            Schema::create('price_list_items', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('price_list_id')->unsigned();
                $table->bigInteger('item_id')->unsigned();
                $table->decimal('price', 15, 3)->default(0.000);
                $table->bigInteger('user_id')->unsigned();
                $table->bigInteger('company_id')->unsigned();
                $table->string('del_status', 20)->default('Live');
                $table->timestamps();

                $table->foreign('price_list_id')->references('id')->on('price_lists')->onDelete('cascade');
                $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
                $table->unique(['price_list_id', 'item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
