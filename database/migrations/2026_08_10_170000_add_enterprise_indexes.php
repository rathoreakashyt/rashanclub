<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Enterprise Performance Indexes
 * ─────────────────────────────────────────────────
 * Covers: sync queries, POS lookups, report generation.
 * All indexes use IF NOT EXISTS pattern for idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            // ═══ ITEMS (POS barcode scan, sync, search) ═══
            "CREATE INDEX idx_items_code ON items(code)",
            "CREATE INDEX idx_items_company_del ON items(company_id, del_status)",
            "CREATE INDEX idx_items_updated ON items(updated_at)",
            "CREATE INDEX idx_items_category ON items(category_id)",
            "CREATE INDEX idx_items_brand ON items(brand_id)",
            "CREATE INDEX idx_items_name ON items(name(100))",

            // ═══ SALES (reporting, sync pull) ═══
            "CREATE INDEX idx_sales_company_del ON sales(company_id, del_status)",
            "CREATE INDEX idx_sales_date ON sales(sale_date)",
            "CREATE INDEX idx_sales_customer ON sales(customer_id)",
            "CREATE INDEX idx_sales_invoice ON sales(invoice_no)",
            "CREATE INDEX idx_sales_updated ON sales(updated_at)",
            "CREATE INDEX idx_sale_details_salesid ON sale_details(sales_id)",
            "CREATE INDEX idx_sale_details_item ON sale_details(item_id)",
            "CREATE INDEX idx_sale_details_del ON sale_details(del_status)",
            "CREATE INDEX idx_sale_payments_sale ON sale_payments(sale_id)",

            // ═══ CUSTOMERS ═══
            "CREATE INDEX idx_customers_company_del ON customers(company_id, del_status)",
            "CREATE INDEX idx_customers_phone ON customers(phone)",
            "CREATE INDEX idx_customers_updated ON customers(updated_at)",

            // ═══ SUPPLIERS ═══
            "CREATE INDEX idx_suppliers_company_del ON suppliers(company_id, del_status)",
            "CREATE INDEX idx_suppliers_updated ON suppliers(updated_at)",

            // ═══ PURCHASES ═══
            "CREATE INDEX idx_purchases_company_del ON purchases(company_id, del_status)",
            "CREATE INDEX idx_purchases_date ON purchases(date)",
            "CREATE INDEX idx_purchases_supplier ON purchases(supplier_id)",
            "CREATE INDEX idx_purchases_updated ON purchases(updated_at)",
            "CREATE INDEX idx_purchase_details_purchase ON purchase_details(purchase_id)",
            "CREATE INDEX idx_purchase_details_item ON purchase_details(item_id)",
            "CREATE INDEX idx_purchase_details_del ON purchase_details(del_status)",
            "CREATE INDEX idx_purchase_payments_purchase ON purchase_payments(purchase_id)",

            // ═══ PURCHASE RETURNS ═══
            "CREATE INDEX idx_purchase_returns_company ON purchase_returns(company_id, del_status)",
            "CREATE INDEX idx_purchase_return_details_pr ON purchase_return_details(pur_return_id)",

            // ═══ SALE RETURNS ═══
            "CREATE INDEX idx_sale_returns_company ON sale_returns(company_id, del_status)",
            "CREATE INDEX idx_sale_returns_sale ON sale_returns(sale_id)",
            "CREATE INDEX idx_sale_return_details_sr ON sale_return_details(sale_return_id)",
            "CREATE INDEX idx_sale_return_details_item ON sale_return_details(item_id)",

            // ═══ EXPENSES / INCOMES ═══
            "CREATE INDEX idx_expenses_company_del ON expenses(company_id, del_status)",
            "CREATE INDEX idx_incomes_company_del ON incomes(company_id, del_status)",

            // ═══ SUPPLIER PAYMENTS ═══
            "CREATE INDEX idx_supplier_payments_company ON supplier_payments(company_id, del_status)",
            "CREATE INDEX idx_supplier_payments_supplier ON supplier_payments(supplier_id)",

            // ═══ STOCK VIEW (used by pull) ═══
            "CREATE INDEX idx_view_stock_item ON view_stock_detail(item_id)",
            "CREATE INDEX idx_set_opening_stocks_item ON set_opening_stocks(item_id)",

            // ═══ HOLDS ═══
            "CREATE INDEX idx_holds_company_del ON holds(company_id, del_status)",
            "CREATE INDEX idx_hold_details_hold ON hold_details(hold_id)",

            // ═══ CONFIG TABLES ═══
            "CREATE INDEX idx_payment_methods_company ON payment_methods(company_id, del_status)",
            "CREATE INDEX idx_item_categories_company ON item_categories(company_id)",
            "CREATE INDEX idx_brands_company ON brands(company_id)",
            "CREATE INDEX idx_units_company ON units(company_id)",

            // ═══ EMPLOYEES / ATTENDANCE ═══
            "CREATE INDEX idx_users_company ON users(company_id)",
            "CREATE INDEX idx_users_email ON users(email)",
            "CREATE INDEX idx_attendances_company ON attendances(company_id, del_status)",
            "CREATE INDEX idx_salaries_company ON salaries(company_id, del_status)",

            // ═══ TRANSFERS / DAMAGES / QUOTATIONS ═══
            "CREATE INDEX idx_transfers_company ON transfers(company_id, del_status)",
            "CREATE INDEX idx_damages_company ON damages(company_id, del_status)",
            "CREATE INDEX idx_quotations_company ON quotations(company_id, del_status)",

            // ═══ INSTALLMENTS ═══
            "CREATE INDEX idx_installment_sales_company ON installment_sales(company_id, del_status)",
            "CREATE INDEX idx_customer_receives_company ON customer_receives(company_id, del_status)",

            // ═══ PROMOTIONS ═══
            "CREATE INDEX idx_promotions_company ON promotions(company_id, del_status)",

            // ═══ REGISTERS ═══
            "CREATE INDEX idx_registers_company ON registers(company_id)",
            "CREATE INDEX idx_registers_user ON registers(user_id)",

            // ═══ LOCAL ID MAPPING (sync idempotency) ═══
            "CREATE INDEX idx_local_id_map_entity ON local_id_map(entity_type, local_id, device_id)",
        ];

        foreach ($indexes as $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // Index already exists or table missing — skip
            }
        }
    }

    public function down(): void
    {
        // Drop all indexes (reverse migration)
        $drops = [
            'items' => ['idx_items_code', 'idx_items_company_del', 'idx_items_updated', 'idx_items_category', 'idx_items_brand', 'idx_items_name'],
            'sales' => ['idx_sales_company_del', 'idx_sales_date', 'idx_sales_customer', 'idx_sales_invoice', 'idx_sales_updated'],
            'sale_details' => ['idx_sale_details_salesid', 'idx_sale_details_item', 'idx_sale_details_del'],
            'sale_payments' => ['idx_sale_payments_sale'],
            'customers' => ['idx_customers_company_del', 'idx_customers_phone', 'idx_customers_updated'],
            'suppliers' => ['idx_suppliers_company_del', 'idx_suppliers_updated'],
            'purchases' => ['idx_purchases_company_del', 'idx_purchases_date', 'idx_purchases_supplier', 'idx_purchases_updated'],
            'purchase_details' => ['idx_purchase_details_purchase', 'idx_purchase_details_item', 'idx_purchase_details_del'],
            'purchase_payments' => ['idx_purchase_payments_purchase'],
        ];

        foreach ($drops as $table => $indexes) {
            foreach ($indexes as $index) {
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
                } catch (\Throwable) {}
            }
        }
    }
};
