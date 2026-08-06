<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_club_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('business_partner_name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->decimal('profit_percentage', 5, 2)->default(50.00);
            $table->integer('redemption_date')->default(1)->comment('Day of month for redemption');
            $table->decimal('min_purchase_amount', 15, 2)->default(10000)->comment('Min monthly purchase to qualify');
            $table->unsignedBigInteger('company_id');
            $table->string('del_status', 20)->default('Live');
            $table->timestamps();
        });

        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('company_id');
            $table->decimal('total_earned', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('total_redeemed', 15, 2)->default(0);
            $table->string('del_status', 20)->default('Live');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('type', 20)->comment('credit / redeem');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->unsignedBigInteger('company_id');
            $table->string('del_status', 20)->default('Live');
            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('customer_wallets')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('business_club_settings');
    }
};
