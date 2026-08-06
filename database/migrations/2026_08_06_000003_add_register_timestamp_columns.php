<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema parity with the desktop app's local SQLite `registers` table.
 *
 * The WPF app stores opening_balance_date_time / closing_balance_date_time /
 * opening_details / payment_methods_sale / others_currency locally, but the
 * server `registers` table lacked them, so the web Register Report
 * (Modules/Report -> registerReport()) crashed with
 * "Unknown column 'opening_balance_date_time'" / "closing_balance_date_time".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('registers', 'opening_balance_date_time')) {
            Schema::table('registers', function (Blueprint $table) {
                $table->dateTime('opening_balance_date_time')->nullable()->after('opening_balance');
                $table->dateTime('closing_balance_date_time')->nullable()->after('opening_balance_date_time');
                $table->text('opening_details')->nullable()->after('closing_balance_date_time');
                $table->text('payment_methods_sale')->nullable()->after('opening_details');
                $table->text('others_currency')->nullable()->after('payment_methods_sale');
            });
        }
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            foreach (['others_currency', 'payment_methods_sale', 'opening_details',
                      'closing_balance_date_time', 'opening_balance_date_time'] as $col) {
                if (Schema::hasColumn('registers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
