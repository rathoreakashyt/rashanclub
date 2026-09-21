<?php

/*
|--------------------------------------------------------------------------
| Feature Activation — single source of truth
|--------------------------------------------------------------------------
| Har menu group aur har submenu item ka feature key yahan define hai.
| is_active=false hone par menu/submenu software (WPF) + cloud (web) dono
| jagah hide ho jaata hai.
|
| 'key'  -> unique feature key (DB feature_activations.feature_key)
| 'name' -> Feature Activation page par dikhne wala naam
| 'group'-> section / group jisme feature dikhega
|--------------------------------------------------------------------------
*/

return [
    'features' => [
        // ── Top-level ──
        ['key' => 'home', 'name' => 'Home', 'group' => 'Main'],
        ['key' => 'dashboard', 'name' => 'Dashboard', 'group' => 'Main'],
        ['key' => 'booking', 'name' => 'Booking', 'group' => 'Main'],
        ['key' => 'outlet', 'name' => 'Outlet', 'group' => 'Main'],
        ['key' => 'outlet_add', 'name' => 'Outlet → Add Outlet', 'group' => 'Main'],
        ['key' => 'outlet_list', 'name' => 'Outlet → List Outlet', 'group' => 'Main'],

        // ── ITEM & STOCK ──
        ['key' => 'item', 'name' => 'Item', 'group' => 'Item & Stock'],
        ['key' => 'item_add', 'name' => 'Item → Add Item', 'group' => 'Item & Stock'],
        ['key' => 'item_list', 'name' => 'Item → List Item', 'group' => 'Item & Stock'],
        ['key' => 'item_bulk_update', 'name' => 'Item → Bulk Item Update', 'group' => 'Item & Stock'],
        ['key' => 'item_bulk_import', 'name' => 'Item → Bulk Item Import', 'group' => 'Item & Stock'],
        ['key' => 'item_opening_import', 'name' => 'Item → Opening Stock Import', 'group' => 'Item & Stock'],

        ['key' => 'item_configuration', 'name' => 'Item Configuration', 'group' => 'Item & Stock'],
        ['key' => 'ic_category_add', 'name' => 'Item Config → Add Category', 'group' => 'Item & Stock'],
        ['key' => 'ic_category_list', 'name' => 'Item Config → List Category', 'group' => 'Item & Stock'],
        ['key' => 'ic_brand_add', 'name' => 'Item Config → Add Brand', 'group' => 'Item & Stock'],
        ['key' => 'ic_brand_list', 'name' => 'Item Config → List Brand', 'group' => 'Item & Stock'],
        ['key' => 'ic_unit_add', 'name' => 'Item Config → Add Unit', 'group' => 'Item & Stock'],
        ['key' => 'ic_unit_list', 'name' => 'Item Config → List Unit', 'group' => 'Item & Stock'],
        ['key' => 'ic_rack_add', 'name' => 'Item Config → Add Rack', 'group' => 'Item & Stock'],
        ['key' => 'ic_rack_list', 'name' => 'Item Config → List Rack', 'group' => 'Item & Stock'],
        ['key' => 'ic_variation_add', 'name' => 'Item Config → Add Variation', 'group' => 'Item & Stock'],
        ['key' => 'ic_variation_list', 'name' => 'Item Config → List Variation', 'group' => 'Item & Stock'],

        ['key' => 'stock', 'name' => 'Stock', 'group' => 'Item & Stock'],
        ['key' => 'stock_view', 'name' => 'Stock → Stock', 'group' => 'Item & Stock'],
        ['key' => 'stock_low', 'name' => 'Stock → Low Stock', 'group' => 'Item & Stock'],
        ['key' => 'inventory', 'name' => 'Inventory', 'group' => 'Item & Stock'],

        // ── SALE & CUSTOMER ──
        ['key' => 'sale', 'name' => 'Sale', 'group' => 'Sale & Customer'],
        ['key' => 'pos', 'name' => 'Sale → POS', 'group' => 'Sale & Customer'],
        ['key' => 'sale_list', 'name' => 'Sale → List Sale', 'group' => 'Sale & Customer'],
        ['key' => 'sale_promotion_add', 'name' => 'Sale → Add Promotion', 'group' => 'Sale & Customer'],
        ['key' => 'sale_promotion_list', 'name' => 'Sale → List Promotion', 'group' => 'Sale & Customer'],
        ['key' => 'sale_delivery_add', 'name' => 'Sale → Add Delivery Partner', 'group' => 'Sale & Customer'],
        ['key' => 'sale_delivery_list', 'name' => 'Sale → List Delivery Partner', 'group' => 'Sale & Customer'],

        ['key' => 'sale_return', 'name' => 'Sale Return', 'group' => 'Sale & Customer'],
        ['key' => 'sale_return_add', 'name' => 'Sale Return → Add', 'group' => 'Sale & Customer'],
        ['key' => 'sale_return_list', 'name' => 'Sale Return → List', 'group' => 'Sale & Customer'],

        ['key' => 'installment_sale', 'name' => 'Installment Sale', 'group' => 'Sale & Customer'],
        ['key' => 'installment_add', 'name' => 'Installment → Add', 'group' => 'Sale & Customer'],
        ['key' => 'installment_list', 'name' => 'Installment → List', 'group' => 'Sale & Customer'],
        ['key' => 'installment_collection', 'name' => 'Installment → Collection', 'group' => 'Sale & Customer'],

        ['key' => 'customer', 'name' => 'Customer', 'group' => 'Sale & Customer'],
        ['key' => 'customer_add', 'name' => 'Customer → Add Customer', 'group' => 'Sale & Customer'],
        ['key' => 'customer_list', 'name' => 'Customer → List Customer', 'group' => 'Sale & Customer'],
        ['key' => 'customer_installment_add', 'name' => 'Customer → Add Installment Customer', 'group' => 'Sale & Customer'],
        ['key' => 'customer_installment_list', 'name' => 'Customer → List Installment Customer', 'group' => 'Sale & Customer'],
        ['key' => 'customer_receive_add', 'name' => 'Customer → Add Customer Receive', 'group' => 'Sale & Customer'],
        ['key' => 'customer_receive_list', 'name' => 'Customer → List Customer Receive', 'group' => 'Sale & Customer'],

        ['key' => 'income', 'name' => 'Income', 'group' => 'Sale & Customer'],
        ['key' => 'income_add', 'name' => 'Income → Add Income', 'group' => 'Sale & Customer'],
        ['key' => 'income_list', 'name' => 'Income → List Income', 'group' => 'Sale & Customer'],
        ['key' => 'income_category_add', 'name' => 'Income → Add Category', 'group' => 'Sale & Customer'],
        ['key' => 'income_category_list', 'name' => 'Income → List Category', 'group' => 'Sale & Customer'],

        // ── PURCHASE & SUPPLIER ──
        ['key' => 'purchase', 'name' => 'Purchase', 'group' => 'Purchase & Supplier'],
        ['key' => 'purchase_add', 'name' => 'Purchase → Add', 'group' => 'Purchase & Supplier'],
        ['key' => 'purchase_list', 'name' => 'Purchase → List', 'group' => 'Purchase & Supplier'],

        ['key' => 'purchase_return', 'name' => 'Purchase Return', 'group' => 'Purchase & Supplier'],
        ['key' => 'purchase_return_add', 'name' => 'Purchase Return → Add', 'group' => 'Purchase & Supplier'],
        ['key' => 'purchase_return_list', 'name' => 'Purchase Return → List', 'group' => 'Purchase & Supplier'],

        ['key' => 'supplier', 'name' => 'Supplier', 'group' => 'Purchase & Supplier'],
        ['key' => 'supplier_add', 'name' => 'Supplier → Add Supplier', 'group' => 'Purchase & Supplier'],
        ['key' => 'supplier_list', 'name' => 'Supplier → List Supplier', 'group' => 'Purchase & Supplier'],
        ['key' => 'supplier_payment_add', 'name' => 'Supplier → Add Payment', 'group' => 'Purchase & Supplier'],
        ['key' => 'supplier_payment_list', 'name' => 'Supplier → List Payment', 'group' => 'Purchase & Supplier'],

        ['key' => 'expense', 'name' => 'Expense', 'group' => 'Purchase & Supplier'],
        ['key' => 'expense_add', 'name' => 'Expense → Add Expense', 'group' => 'Purchase & Supplier'],
        ['key' => 'expense_list', 'name' => 'Expense → List Expense', 'group' => 'Purchase & Supplier'],
        ['key' => 'expense_category_add', 'name' => 'Expense → Add Category', 'group' => 'Purchase & Supplier'],
        ['key' => 'expense_category_list', 'name' => 'Expense → List Category', 'group' => 'Purchase & Supplier'],

        // ── TRANSFER & DAMAGE ──
        ['key' => 'transfer', 'name' => 'Transfer', 'group' => 'Transfer & Damage'],
        ['key' => 'transfer_add', 'name' => 'Transfer → Add', 'group' => 'Transfer & Damage'],
        ['key' => 'transfer_list', 'name' => 'Transfer → List', 'group' => 'Transfer & Damage'],

        ['key' => 'damage', 'name' => 'Damage', 'group' => 'Transfer & Damage'],
        ['key' => 'damage_add', 'name' => 'Damage → Add', 'group' => 'Transfer & Damage'],
        ['key' => 'damage_list', 'name' => 'Damage → List', 'group' => 'Transfer & Damage'],

        ['key' => 'quotation', 'name' => 'Quotation', 'group' => 'Transfer & Damage'],
        ['key' => 'quotation_add', 'name' => 'Quotation → Add', 'group' => 'Transfer & Damage'],
        ['key' => 'quotation_list', 'name' => 'Quotation → List', 'group' => 'Transfer & Damage'],

        // ── FIXED ASSET ──
        ['key' => 'fixed_asset', 'name' => 'Fixed Asset', 'group' => 'Fixed Asset'],
        ['key' => 'fixed_asset_add', 'name' => 'Fixed Asset → Add Item', 'group' => 'Fixed Asset'],
        ['key' => 'fixed_asset_list', 'name' => 'Fixed Asset → List Item', 'group' => 'Fixed Asset'],
        ['key' => 'fixed_asset_stock_in_add', 'name' => 'Fixed Asset → Add Stock In', 'group' => 'Fixed Asset'],
        ['key' => 'fixed_asset_stock_in_list', 'name' => 'Fixed Asset → List Stock In', 'group' => 'Fixed Asset'],
        ['key' => 'fixed_asset_stock_out_add', 'name' => 'Fixed Asset → Add Stock Out', 'group' => 'Fixed Asset'],
        ['key' => 'fixed_asset_stock_out_list', 'name' => 'Fixed Asset → List Stock Out', 'group' => 'Fixed Asset'],

        // ── BUSINESS CLUB ──
        ['key' => 'business_club', 'name' => 'Business Club', 'group' => 'Business Club'],
        ['key' => 'business_club_dashboard', 'name' => 'Business Club → Dashboard', 'group' => 'Business Club'],
        ['key' => 'business_club_settings', 'name' => 'Business Club → Settings', 'group' => 'Business Club'],
        ['key' => 'business_club_wallets', 'name' => 'Business Club → Customer Wallets', 'group' => 'Business Club'],
        ['key' => 'business_club_register', 'name' => 'Business Club → Register Member', 'group' => 'Business Club'],
        ['key' => 'business_club_redeem', 'name' => 'Business Club → Redeem Profit', 'group' => 'Business Club'],
        ['key' => 'business_club_id_card', 'name' => 'Business Club → Print ID Card', 'group' => 'Business Club'],

        // ── PRICE LISTS ──
        ['key' => 'price_list', 'name' => 'Price Lists', 'group' => 'Price Lists'],
        ['key' => 'price_list_add', 'name' => 'Price Lists → Add', 'group' => 'Price Lists'],
        ['key' => 'price_list_list', 'name' => 'Price Lists → List', 'group' => 'Price Lists'],

        // ── WARRANTY & SERVICING ──
        ['key' => 'warranty_servicing', 'name' => 'Warranty & Servicing', 'group' => 'Warranty & Servicing'],
        ['key' => 'servicing_add', 'name' => 'Warranty → Add Servicing', 'group' => 'Warranty & Servicing'],
        ['key' => 'servicing_list', 'name' => 'Warranty → List Servicing', 'group' => 'Warranty & Servicing'],
        ['key' => 'warranty_add', 'name' => 'Warranty → Add Warranty', 'group' => 'Warranty & Servicing'],
        ['key' => 'warranty_list', 'name' => 'Warranty → List Warranty', 'group' => 'Warranty & Servicing'],
        ['key' => 'warranty_checking', 'name' => 'Warranty → Checking', 'group' => 'Warranty & Servicing'],

        // ── ACCOUNTING ──
        ['key' => 'payment_account', 'name' => 'Payment Account', 'group' => 'Accounting'],
        ['key' => 'payment_account_add', 'name' => 'Payment Account → Add', 'group' => 'Accounting'],
        ['key' => 'payment_account_list', 'name' => 'Payment Account → List', 'group' => 'Accounting'],
        ['key' => 'payment_account_sort', 'name' => 'Payment Account → Sort', 'group' => 'Accounting'],

        ['key' => 'accounting', 'name' => 'Accounting', 'group' => 'Accounting'],
        ['key' => 'accounting_deposit_add', 'name' => 'Accounting → Add Deposit/Withdraw', 'group' => 'Accounting'],
        ['key' => 'accounting_deposit_list', 'name' => 'Accounting → List Deposit/Withdraw', 'group' => 'Accounting'],
        ['key' => 'accounting_balance', 'name' => 'Accounting → Account Balance', 'group' => 'Accounting'],
        ['key' => 'accounting_statement', 'name' => 'Accounting → Account Statement', 'group' => 'Accounting'],
        ['key' => 'accounting_balance_sheet', 'name' => 'Accounting → Balance Sheet', 'group' => 'Accounting'],
        ['key' => 'accounting_trial_balance', 'name' => 'Accounting → Trial Balance', 'group' => 'Accounting'],
        ['key' => 'accounting_transaction_history', 'name' => 'Accounting → Transaction History', 'group' => 'Accounting'],
        ['key' => 'gst', 'name' => 'GST', 'group' => 'Accounting'],

        // ── MARKETING ──
        ['key' => 'marketing', 'name' => 'Marketing', 'group' => 'Marketing'],
        ['key' => 'marketing_email', 'name' => 'Marketing → Email Marketing', 'group' => 'Marketing'],
        ['key' => 'marketing_sms', 'name' => 'Marketing → SMS Marketing', 'group' => 'Marketing'],
        ['key' => 'marketing_whatsapp', 'name' => 'Marketing → WhatsApp Marketing', 'group' => 'Marketing'],

        // ── HRM ──
        ['key' => 'hrm', 'name' => 'Human Resource Management', 'group' => 'Human Resource'],
        ['key' => 'role_permission', 'name' => 'Role Permission', 'group' => 'Human Resource'],
        ['key' => 'role_add', 'name' => 'Role → Add', 'group' => 'Human Resource'],
        ['key' => 'role_list', 'name' => 'Role → List', 'group' => 'Human Resource'],

        ['key' => 'employee_account', 'name' => 'Employee Account', 'group' => 'Human Resource'],
        ['key' => 'employee_add', 'name' => 'Employee → Add Employee', 'group' => 'Human Resource'],
        ['key' => 'employee_list', 'name' => 'Employee → List Employee', 'group' => 'Human Resource'],
        ['key' => 'employee_update_profile', 'name' => 'Employee → Update Profile', 'group' => 'Human Resource'],

        ['key' => 'attendance', 'name' => 'Attendance', 'group' => 'Human Resource'],
        ['key' => 'attendance_add', 'name' => 'Attendance → Add', 'group' => 'Human Resource'],
        ['key' => 'attendance_list', 'name' => 'Attendance → List', 'group' => 'Human Resource'],

        ['key' => 'salary', 'name' => 'Salary / Payroll', 'group' => 'Human Resource'],
        ['key' => 'salary_add', 'name' => 'Salary → Add', 'group' => 'Human Resource'],
        ['key' => 'salary_list', 'name' => 'Salary → List', 'group' => 'Human Resource'],

        ['key' => 'employee_advance', 'name' => 'Employee Advance Payment', 'group' => 'Human Resource'],
        ['key' => 'employee_advance_add', 'name' => 'Employee Advance → Add', 'group' => 'Human Resource'],
        ['key' => 'employee_advance_list', 'name' => 'Employee Advance → List', 'group' => 'Human Resource'],

        // ── REPORT & SETTING ──
        ['key' => 'report', 'name' => 'Report', 'group' => 'Report & Setting'],
        ['key' => 'report_all', 'name' => 'Report → All Reports', 'group' => 'Report & Setting'],

        ['key' => 'settings', 'name' => 'Settings', 'group' => 'Report & Setting'],
        ['key' => 'settings_all', 'name' => 'Settings → All Settings', 'group' => 'Report & Setting'],
        ['key' => 'feature_activation', 'name' => 'Settings → Feature Activation', 'group' => 'Report & Setting'],
        ['key' => 'denomination_add', 'name' => 'Settings → Add Denomination', 'group' => 'Report & Setting'],
        ['key' => 'denomination_list', 'name' => 'Settings → List Denomination', 'group' => 'Report & Setting'],
        ['key' => 'currency_add', 'name' => 'Settings → Add Multiple Currency', 'group' => 'Report & Setting'],
        ['key' => 'currency_list', 'name' => 'Settings → List Multiple Currency', 'group' => 'Report & Setting'],
        ['key' => 'printer_add', 'name' => 'Settings → Add Printer', 'group' => 'Report & Setting'],
        ['key' => 'printer_list', 'name' => 'Settings → List Printer', 'group' => 'Report & Setting'],
        ['key' => 'counter_add', 'name' => 'Settings → Add Counter', 'group' => 'Report & Setting'],
        ['key' => 'counter_list', 'name' => 'Settings → List Counter', 'group' => 'Report & Setting'],
        ['key' => 'module_add', 'name' => 'Settings → Add Module', 'group' => 'Report & Setting'],
        ['key' => 'module_list', 'name' => 'Settings → List Modules', 'group' => 'Report & Setting'],
    ],
];