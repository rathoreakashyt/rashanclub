<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify existing business_club_settings table
        Schema::table('business_club_settings', function (Blueprint $table) {
            // Add new columns (keep existing ones for backward compat)
            if (!Schema::hasColumn('business_club_settings', 'membership_amount')) {
                $table->decimal('membership_amount', 15, 2)->default(10000)->after('min_purchase_amount');
            }
            if (!Schema::hasColumn('business_club_settings', 'profit_share_percentage')) {
                $table->decimal('profit_share_percentage', 5, 2)->default(50)->after('membership_amount');
            }
            if (!Schema::hasColumn('business_club_settings', 'redemption_day')) {
                $table->integer('redemption_day')->default(1)->after('profit_share_percentage');
            }
            if (!Schema::hasColumn('business_club_settings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('redemption_day');
            }
        });

        // Create new business_club_members table
        Schema::create('business_club_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 20)->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('company_id');
            $table->decimal('membership_amount', 15, 2)->default(0);
            $table->decimal('locked_balance', 15, 2)->default(0);
            $table->decimal('earned_balance', 15, 2)->default(0);
            $table->decimal('total_earned', 15, 2)->default(0);
            $table->decimal('total_redeemed', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->dateTime('joined_at')->nullable();
            $table->string('del_status', 20)->default('Live');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'company_id', 'del_status'], 'bcm_cust_comp_del_idx');
            $table->index(['company_id', 'del_status', 'status'], 'bcm_comp_del_status_idx');
        });

        // Create new business_club_transactions table
        Schema::create('business_club_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->enum('type', ['membership_deposit', 'profit_credit', 'redemption']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->unsignedBigInteger('company_id');
            $table->string('del_status', 20)->default('Live');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('business_club_members')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->index(['member_id', 'del_status'], 'bct_member_del_idx');
            $table->index(['customer_id', 'company_id', 'del_status'], 'bct_cust_comp_del_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_club_transactions');
        Schema::dropIfExists('business_club_members');

        Schema::table('business_club_settings', function (Blueprint $table) {
            $columns = ['membership_amount', 'profit_share_percentage', 'redemption_day', 'is_active'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('business_club_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
