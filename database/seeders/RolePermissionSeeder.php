<?php

namespace Database\Seeders;


use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        $adminUser = User::where('email', 'admin@example.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Super Admin',
                'role' => 1,
                'email' => 'admin@example.com',
                'password' => '123456',
                'question' => 'What is the name of your first pet?',
                'answer' => 'Mickey',
                'company_id' => 1,
                'del_status' => 'Live',
                'email_verified_at' => now(),
            ]);
        }
        $adminUser->assignRole('Super Admin');
        
        $permissions = [
            'accounting' => [
                'account_balance', 'account_statement', 'balancesheet', 'trial_balance', 'transaction_history'
            ],
            'attendance' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'booking' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'brand' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'category' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'customer' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'counter' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'customer_receive' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'dashboard' => [
                'dashboard', 'userhome',
            ],
            'damage' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'delivery_partner' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'denomination' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'deposit_withdraw' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'expense' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'expense_category' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'fixed_asset_item' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'fixed_asset_stock_in' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'fixed_asset_stock_out' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'income' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'income_category' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'installment_sale' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'item' => [
                'list', 'create', 'edit', 'show', 'destroy', 'import'
            ],
            'item_category' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'marketing' => [
                'email', 'sms', 'whatsapp'
            ],
            'multiple_currency' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'outlet' => [
                'list', 'create', 'edit', 'show', 'destroy', 'enter'
            ],
            'payment_method' => [
                'list', 'create', 'edit', 'show', 'destroy', 'sort'
            ],
            'permission' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'printer' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'promotion' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'businessclub' => [
                'list', 'settings', 'wallets'
            ],
            'price_list' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'purchase' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'purchase_return' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'quotation' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'rack' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'report' => [
                'register_report', 'z_report', 'daily_summary_report', 'sale_report', 'due_sale_report',
                'final_invoice_due_report', 'service_sale_report', 'combo_service_report', 'stock_report', 'low_stock_report',
                'expire_soon_report', 'employee_sale_report', 'customer_receive_report', 'attendance_report', 'product_profit_report',
                'supplier_ledger_report', 'supplier_balance_report', 'customer_ledger_report', 'customer_balance_report',
                'servicing_report', 'product_sale_report', 'tax_report', 'detailed_sale_report', 'profit_loss_report',
                'purchase_report', 'expense_report', 'income_report', 'salary_report', 'purchase_return_report',
                'sale_return_report', 'damage_report', 'installment_report', 'installment_due_report', 'item_tracking_report',
                'price_history_report', 'cash_flow_report', 'available_loyalty_point_report', 'usage_loyalty_point_report',
                'scheme_report',
            ],
            'role' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'salary' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'employee_advance_payment' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'sale' => [
                'list', 'create', 'edit', 'show', 'destroy', 'pos'
            ],
            'stock' => [
                'stock', 'low_stock'
            ],
            'sale_return' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'servicing' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'setting' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'supplier' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'supplier_payment' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'transfer' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'unit' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'user' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'variation_attribute' => [
                'list', 'create', 'edit', 'show', 'destroy'
            ],
            'warranty' => [
                'list', 'create', 'edit', 'show', 'destroy', 'checking'
            ],
        ];

        foreach ($permissions as $group => $permissionList) {
            foreach ($permissionList as $permission) {
                Permission::firstOrCreate([
                    'name' => $group.'-'.$permission,
                    'group_name' => $group,
                    'guard_name' => 'web' // Using 'web' as default guard
                ]);
            }
        }

        // Create Super Admin role
        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        $allPermissions = Permission::all();
        if ($allPermissions->isEmpty()) {
            throw new \Exception('No permissions found to sync');
        }
        $superAdminRole->syncPermissions($allPermissions);

        // Assign role to the admin user
        $adminUser->assignRole($superAdminRole);

        // Clear cache after role assignment
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

       
    }
}
