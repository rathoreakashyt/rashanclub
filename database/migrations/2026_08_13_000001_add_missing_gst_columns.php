<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds columns the application code expects but which were never
 * created because the database was built from an older dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sale_details', 'menu_taxes')) {
            Schema::table('sale_details', function (Blueprint $table) {
                $table->text('menu_taxes')->nullable()->after('item_tax_amount');
            });
        }

        if (!Schema::hasColumn('holds', 'hold_no')) {
            Schema::table('holds', function (Blueprint $table) {
                $table->string('hold_no', 255)->nullable()->after('invoice_no');
            });
        }

        if (!Schema::hasColumn('servicings', 'product_name')) {
            Schema::table('servicings', function (Blueprint $table) {
                $table->string('product_name', 255)->nullable()->after('reference_no');
            });
        }

        if (!Schema::hasColumn('warranties', 'product_name')) {
            Schema::table('warranties', function (Blueprint $table) {
                $table->string('product_name', 255)->nullable()->after('reference_no');
            });
        }

        if (!Schema::hasColumn('sales', 'zatca_phase1_qr_code')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->text('zatca_phase1_qr_code')->nullable()->after('sale_vat_objects');
            });
        }

        if (!Schema::hasColumn('sales', 'zatca_compliant')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->boolean('zatca_compliant')->default(false)->after('zatca_phase1_qr_code');
            });
        }

        if (!Schema::hasColumn('sales', 'zatca_invoice_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unsignedBigInteger('zatca_invoice_id')->nullable()->after('zatca_compliant');
            });
        }

        if (!Schema::hasColumn('sales', 'total_items')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->integer('total_items')->nullable()->after('sale_no');
            });
        }

        if (!Schema::hasColumn('sales', 'online_yes_no')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('online_yes_no', 10)->nullable()->default('No')->after('grand_total');
            });
        }

        if (!Schema::hasColumn('sale_details', 'item_type')) {
            Schema::table('sale_details', function (Blueprint $table) {
                $table->string('item_type', 50)->nullable()->after('discount_amount');
            });
        }

        if (!Schema::hasColumn('sale_details', 'expiry_imei_serial')) {
            Schema::table('sale_details', function (Blueprint $table) {
                $table->text('expiry_imei_serial')->nullable()->after('item_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn('menu_taxes');
        });
        Schema::table('holds', function (Blueprint $table) {
            $table->dropColumn('hold_no');
        });
        Schema::table('servicings', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
        Schema::table('warranties', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['zatca_phase1_qr_code', 'zatca_compliant', 'zatca_invoice_id']);
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['total_items', 'online_yes_no']);
        });
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'expiry_imei_serial']);
        });
    }
};