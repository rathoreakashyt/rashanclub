<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class SearchController extends Controller
{
    /**
     * Get menu search data
     */
    public function getSearchData(Request $request)
    {
        try {
            $menuType = $request->get('type', 'vertical'); // vertical or horizontal
            
            $data = [
                'navigation' => $this->getNavigationItems(),
                'suggestions' => $this->getSuggestions()
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('SearchController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'navigation' => [],
                'suggestions' => []
            ], 200);
        }
    }

    /**
     * Safely get route URL, return null if route doesn't exist
     */
    private function safeRoute($name, ...$parameters)
    {
        try {
            if (\Route::has($name)) {
                if (empty($parameters)) {
                    return route($name);
                } elseif (count($parameters) === 1) {
                    // Single parameter - pass directly
                    return route($name, $parameters[0]);
                } else {
                    // Multiple parameters - pass as array
                    return route($name, $parameters);
                }
            }
        } catch (\Exception $e) {
            // Route doesn't exist or error generating URL
            \Log::debug('Route not found or error: ' . $name . ' - ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Filter navigation items to remove entries with null URLs
     */
    private function filterNavigationItems($navigation)
    {
        $filtered = [];
        foreach ($navigation as $section => $items) {
            $filteredItems = array_filter($items, function($item) {
                return !empty($item['url']);
            });
            if (!empty($filteredItems)) {
                $filtered[$section] = array_values($filteredItems);
            }
        }
        return $filtered;
    }

    /**
     * Get navigation items from menu structure
     */
    private function getNavigationItems()
    {
        $navigation = [];
        
        try {

        // Dashboards
        $navigation['Dashboards'] = [
            [
                'name' => __('Home'),
                'icon' => 'tabler-home',
                'url' => url('/user-home')
            ],
            [
                'name' => __('Dashboard'),
                'icon' => 'tabler-gauge',
                'url' => url('/dashboard')
            ],
            [
                'name' => __('Booking'),
                'icon' => 'tabler-calendar',
                'url' => url('/booking')
            ]
        ];

        // Outlet
        $navigation['Outlet'] = [
            [
                'name' => __('Add') . ' ' . __('Outlet'),
                'icon' => 'tabler-layout-grid',
                'url' => $this->safeRoute('outlet.create')
            ],
            [
                'name' => __('List') . ' ' . __('Outlet'),
                'icon' => 'tabler-layout-grid',
                'url' => $this->safeRoute('outlet.index')
            ]
        ];

        // Item & Stock
        $navigation['Item & Stock'] = [
            [
                'name' => __('Add') . ' ' . __('Item'),
                'icon' => 'tabler-heart-pin',
                'url' => $this->safeRoute('item.create')
            ],
            [
                'name' => __('List') . ' ' . __('Item'),
                'icon' => 'tabler-heart-pin',
                'url' => $this->safeRoute('item.index')
            ],
            [
                'name' => __('Bulk_Product_Update'),
                'icon' => 'tabler-heart-pin',
                'url' => $this->safeRoute('bulk-item-import')
            ],
            [
                'name' => __('Bulk') . ' ' . __('Item') . ' ' . __('Update'),
                'icon' => 'tabler-heart-pin',
                'url' => $this->safeRoute('bulk-item-update')
            ],
            [
                'name' => __('Add') . ' ' . __('Item') . ' ' . __('Category'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('item-category.create')
            ],
            [
                'name' => __('List') . ' ' . __('Item') . ' ' . __('Category'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('item-category.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Brand'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('brand.create')
            ],
            [
                'name' => __('List') . ' ' . __('Brand'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('brand.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Unit'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('unit.create')
            ],
            [
                'name' => __('List') . ' ' . __('Unit'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('unit.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Rack'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('rack.create')
            ],
            [
                'name' => __('List') . ' ' . __('Rack'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('rack.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Variation') . ' ' . __('Attribute'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('variation-attribute.create')
            ],
            [
                'name' => __('List') . ' ' . __('Variation') . ' ' . __('Attribute'),
                'icon' => 'tabler-settings-heart',
                'url' => $this->safeRoute('variation-attribute.index')
            ],
            [
                'name' => __('Stock'),
                'icon' => 'tabler-replace',
                'url' => $this->safeRoute('stock.index')
            ],
            [
                'name' => __('Low') . ' ' . __('Stock'),
                'icon' => 'tabler-replace',
                'url' => $this->safeRoute('stock.low-stock')
            ]
        ];

        // Sale & Customer
        $navigation['Sale & Customer'] = [
            [
                'name' => __('POS'),
                'icon' => 'tabler-shopping-cart-heart',
                'url' => $this->safeRoute('pos.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Promotion'),
                'icon' => 'tabler-shopping-cart-heart',
                'url' => $this->safeRoute('promotion.create')
            ],
            [
                'name' => __('List') . ' ' . __('Promotion'),
                'icon' => 'tabler-shopping-cart-heart',
                'url' => $this->safeRoute('promotion.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Delivery') . ' ' . __('Partner'),
                'icon' => 'tabler-shopping-cart-heart',
                'url' => $this->safeRoute('delivery-partner.create')
            ],
            [
                'name' => __('List') . ' ' . __('Delivery') . ' ' . __('Partner'),
                'icon' => 'tabler-shopping-cart-heart',
                'url' => $this->safeRoute('delivery-partner.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Sale') . ' ' . __('Return'),
                'icon' => 'tabler-u-turn-right',
                'url' => $this->safeRoute('sale-return.create')
            ],
            [
                'name' => __('List') . ' ' . __('Sale') . ' ' . __('Return'),
                'icon' => 'tabler-u-turn-right',
                'url' => $this->safeRoute('sale-return.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Installment') . ' ' . __('Sale'),
                'icon' => 'tabler-stack-middle',
                'url' => $this->safeRoute('installment-sale.create')
            ],
            [
                'name' => __('List') . ' ' . __('Installment') . ' ' . __('Sale'),
                'icon' => 'tabler-stack-middle',
                'url' => $this->safeRoute('installment-sale.index')
            ],
            [
                'name' => __('Installment') . ' ' . __('Collection'),
                'icon' => 'tabler-stack-middle',
                'url' => $this->safeRoute('installment-collection.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Customer'),
                'icon' => 'tabler-user-plus',
                'url' => $this->safeRoute('customer.create')
            ],
            [
                'name' => __('List') . ' ' . __('Customer'),
                'icon' => 'tabler-user-plus',
                'url' => $this->safeRoute('customer.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Installment') . ' ' . __('Customer'),
                'icon' => 'tabler-user-plus',
                'url' => $this->safeRoute('installment-customer.create')
            ],
            [
                'name' => __('List') . ' ' . __('Installment') . ' ' . __('Customer'),
                'icon' => 'tabler-user-plus',
                'url' => $this->safeRoute('installment-customer.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Due') . ' ' . __('Receive'),
                'icon' => 'tabler-user-plus',
                'url' => $this->safeRoute('customer-receive.create')
            ],
            [
                'name' => __('List') . ' ' . __('Due') . ' ' . __('Receive'),
                'icon' => 'tabler-user-plus',
                'url' => $this->safeRoute('customer-receive.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Income'),
                'icon' => 'tabler-circle-plus',
                'url' => $this->safeRoute('income.create')
            ],
            [
                'name' => __('List') . ' ' . __('Income'),
                'icon' => 'tabler-circle-plus',
                'url' => $this->safeRoute('income.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Income') . ' ' . __('Category'),
                'icon' => 'tabler-circle-plus',
                'url' => $this->safeRoute('income-category.create')
            ],
            [
                'name' => __('List') . ' ' . __('Income') . ' ' . __('Category'),
                'icon' => 'tabler-circle-plus',
                'url' => $this->safeRoute('income-category.index')
            ]
        ];

        // Purchase & Supplier
        $navigation['Purchase & Supplier'] = [
            [
                'name' => __('Add') . ' ' . __('Purchase'),
                'icon' => 'tabler-basket-down',
                'url' => $this->safeRoute('purchase.create')
            ],
            [
                'name' => __('List') . ' ' . __('Purchase'),
                'icon' => 'tabler-basket-down',
                'url' => $this->safeRoute('purchase.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Purchase') . ' ' . __('Return'),
                'icon' => 'tabler-u-turn-left',
                'url' => $this->safeRoute('purchase-return.create')
            ],
            [
                'name' => __('List') . ' ' . __('Purchase') . ' ' . __('Return'),
                'icon' => 'tabler-u-turn-left',
                'url' => $this->safeRoute('purchase-return.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Supplier'),
                'icon' => 'tabler-user-minus',
                'url' => $this->safeRoute('supplier.create')
            ],
            [
                'name' => __('List') . ' ' . __('Supplier'),
                'icon' => 'tabler-user-minus',
                'url' => $this->safeRoute('supplier.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Supplier') . ' ' . __('Payment'),
                'icon' => 'tabler-user-minus',
                'url' => $this->safeRoute('supplier-payment.create')
            ],
            [
                'name' => __('List') . ' ' . __('Supplier') . ' ' . __('Payment'),
                'icon' => 'tabler-user-minus',
                'url' => $this->safeRoute('supplier-payment.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Expense'),
                'icon' => 'tabler-circle-minus',
                'url' => $this->safeRoute('expense.create')
            ],
            [
                'name' => __('List') . ' ' . __('Expense'),
                'icon' => 'tabler-circle-minus',
                'url' => $this->safeRoute('expense.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Expense') . ' ' . __('Category'),
                'icon' => 'tabler-circle-minus',
                'url' => $this->safeRoute('expense-category.create')
            ],
            [
                'name' => __('List') . ' ' . __('Expense') . ' ' . __('Category'),
                'icon' => 'tabler-circle-minus',
                'url' => $this->safeRoute('expense-category.index')
            ]
        ];

        // Transfer & Damage
        $navigation['Transfer & Damage'] = [
            [
                'name' => __('Add') . ' ' . __('Transfer'),
                'icon' => 'tabler-truck-delivery',
                'url' => $this->safeRoute('transfer.create')
            ],
            [
                'name' => __('List') . ' ' . __('Transfer'),
                'icon' => 'tabler-truck-delivery',
                'url' => $this->safeRoute('transfer.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Damage'),
                'icon' => 'tabler-trash',
                'url' => $this->safeRoute('damage.create')
            ],
            [
                'name' => __('List') . ' ' . __('Damage'),
                'icon' => 'tabler-trash',
                'url' => $this->safeRoute('damage.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Quotation'),
                'icon' => 'tabler-pencil-star',
                'url' => $this->safeRoute('quotation.create')
            ],
            [
                'name' => __('List') . ' ' . __('Quotation'),
                'icon' => 'tabler-pencil-star',
                'url' => $this->safeRoute('quotation.index')
            ]
        ];

        // Fixed Assets
        $navigation['Fixed Asset'] = [
            [
                'name' => __('Add') . ' ' . __('Item'),
                'icon' => 'tabler-tournament',
                'url' => $this->safeRoute('fixed-asset-item.create')
            ],
            [
                'name' => __('List') . ' ' . __('Item'),
                'icon' => 'tabler-tournament',
                'url' => $this->safeRoute('fixed-asset-item.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Stock') . ' ' . __('In'),
                'icon' => 'tabler-tournament',
                'url' => $this->safeRoute('fixed-asset-stock-in.create')
            ],
            [
                'name' => __('List') . ' ' . __('Stock') . ' ' . __('In'),
                'icon' => 'tabler-tournament',
                'url' => $this->safeRoute('fixed-asset-stock-in.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Stock') . ' ' . __('Out'),
                'icon' => 'tabler-tournament',
                'url' => $this->safeRoute('fixed-asset-stock-out.create')
            ],
            [
                'name' => __('List') . ' ' . __('Stock') . ' ' . __('Out'),
                'icon' => 'tabler-tournament',
                'url' => $this->safeRoute('fixed-asset-stock-out.index')
            ]
        ];

        // Accounting
        $navigation['Accounting'] = [
            [
                'name' => __('Add') . ' ' . __('Payment') . ' ' . __('Account'),
                'icon' => 'tabler-building-bank',
                'url' => $this->safeRoute('payment-method.create')
            ],
            [
                'name' => __('List') . ' ' . __('Payment') . ' ' . __('Account'),
                'icon' => 'tabler-building-bank',
                'url' => $this->safeRoute('payment-method.index')
            ],
            [
                'name' => __('Sort') . ' ' . __('Account'),
                'icon' => 'tabler-building-bank',
                'url' => $this->safeRoute('deposit-withdraw.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Deposit') . '/' . __('Withdraw'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('deposit-withdraw.create')
            ],
            [
                'name' => __('List') . ' ' . __('Deposit') . '/' . __('Withdraw'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('deposit-withdraw.index')
            ],
            [
                'name' => __('Account') . ' ' . __('Balance'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('accounting.reports.account-balance.view')
            ],
            [
                'name' => __('Account') . ' ' . __('Statement'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('accounting.reports.account-statement.view')
            ],
            [
                'name' => __('Balance') . ' ' . __('Sheet'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('accounting.reports.balance-sheet.view')
            ],
            [
                'name' => __('Trial') . ' ' . __('Balance'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('accounting.reports.trial-balance.view')
            ],
            [
                'name' => __('Transaction') . ' ' . __('History'),
                'icon' => 'tabler-receipt-dollar',
                'url' => $this->safeRoute('accounting.reports.transaction-history.view')
            ]
        ];

        // Marketing
        $navigation['Marketing'] = [
            [
                'name' => __('Email') . ' ' . __('Marketing'),
                'icon' => 'tabler-ad-2',
                'url' => $this->safeRoute('email.marketing')
            ],
            [
                'name' => __('SMS') . ' ' . __('Marketing'),
                'icon' => 'tabler-ad-2',
                'url' => $this->safeRoute('sms.marketing')
            ],
            [
                'name' => __('WhatsApp') . ' ' . __('Marketing'),
                'icon' => 'tabler-ad-2',
                'url' => $this->safeRoute('whatsapp.marketing')
            ]
        ];

        // Reports
        $navigation['Reports'] = [
            [
                'name' => __('Daily') . ' ' . __('Summary') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.daily-summary-report')
            ],
            [
                'name' => __('Sale') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.sale-report')
            ],
            [
                'name' => __('Due') . ' ' . __('Sale') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.due-sale-report')
            ],
            [
                'name' => __('Final') . ' ' . __('Invoice') . ' ' . __('Due') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.final-invoice-due-report')
            ],
            [
                'name' => __('Service') . ' ' . __('Sale') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.service-sale-report')
            ],
            [
                'name' => __('Combo') . ' ' . __('Service') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.combo-service-report.view')
            ],
            [
                'name' => __('Stock') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.stock-report')
            ],
            [
                'name' => __('Low') . ' ' . __('Stock') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.low-stock-report')
            ],
            [
                'name' => __('Expire') . ' ' . __('Soon') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.expire-soon-report.view')
            ],
            [
                'name' => __('Employee') . ' ' . __('Sale') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.employee-sale-report')
            ],
            [
                'name' => __('Customer') . ' ' . __('Receive') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.customer-receive-report')
            ],
            [
                'name' => __('Attendance') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.attendance-report')
            ],
            [
                'name' => __('Product') . ' ' . __('Profit') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.product-profit-report')
            ],
            [
                'name' => __('Supplier') . ' ' . __('Ledger') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.supplier-ledger-report')
            ],
            [
                'name' => __('Supplier') . ' ' . __('Balance') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.supplier-balance-report')
            ],
            [
                'name' => __('Customer') . ' ' . __('Ledger') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.customer-ledger-report')
            ],
            [
                'name' => __('Customer') . ' ' . __('Balance') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.customer-balance-report')
            ],
            [
                'name' => __('Servicing') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.servicing-report.view')
            ],
            [
                'name' => __('Product') . ' ' . __('Sale') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.product-sale-report')
            ],
            [
                'name' => __('Tax') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.tax-report')
            ],
            [
                'name' => __('Detailed') . ' ' . __('Sale') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.detailed-sale-report')
            ],
            [
                'name' => __('Profit') . ' ' . __('Loss') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.profit-loss-report.view')
            ],
            [
                'name' => __('Purchase') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.purchase-report')
            ],
            [
                'name' => __('Expense') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.expense-report')
            ],
            [
                'name' => __('Income') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.income-report')
            ],
            [
                'name' => __('Salary') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.salary-report')
            ],
            [
                'name' => __('Purchase') . ' ' . __('Return') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.purchase-return-report')
            ],
            [
                'name' => __('Sale') . ' ' . __('Return') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.sale-return-report')
            ],
            [
                'name' => __('Damage') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.damage-report')
            ],
            [
                'name' => __('Installment') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.installment-report.view')
            ],
            [
                'name' => __('Installment') . ' ' . __('Due') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.installment-due-report.view')
            ],
            [
                'name' => __('Item') . ' ' . __('Tracking') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.item-tracking-report.view')
            ],
            [
                'name' => __('Price') . ' ' . __('History') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.price-history-report.view')
            ],
            [
                'name' => __('Cash') . ' ' . __('Flow') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.cash-flow-report.view')
            ],
            [
                'name' => __('Available') . ' ' . __('Loyalty') . ' ' . __('Point') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.available-loyalty-point-report.view')
            ],
            [
                'name' => __('Usage') . ' ' . __('Loyalty') . ' ' . __('Point') . ' ' . __('Report'),
                'icon' => 'tabler-book-2',
                'url' => $this->safeRoute('report.usage-loyalty-point-report.view')
            ]
        ];

        // Settings
        $navigation['Settings'] = [
            [
                'name' => __('All') . ' ' . __('Setting'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('setting', 'business_setting')
            ],
            [
                'name' => __('Add') . ' ' . __('Denomination'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('denomination.create')
            ],
            [
                'name' => __('List') . ' ' . __('Denomination'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('denomination.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Multiple_Currency'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('multiple-currency.create')
            ],
            [
                'name' => __('List') . ' ' . __('Multiple_Currency'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('multiple-currency.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Printer'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('printer.create')
            ],
            [
                'name' => __('List') . ' ' . __('Printer'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('printer.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Counter'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('counter.create')
            ],
            [
                'name' => __('List') . ' ' . __('Counter'),
                'icon' => 'tabler-settings',
                'url' => $this->safeRoute('counter.index')
            ]
        ];

        // Human Resource Management
        $navigation['Human Resource'] = [
            [
                'name' => __('Add') . ' ' . __('Role') . ' ' . __('Permission'),
                'icon' => 'tabler-fingerprint',
                'url' => $this->safeRoute('role.create')
            ],
            [
                'name' => __('Add') . ' ' . __('Employee'),
                'icon' => 'tabler-password-user',
                'url' => $this->safeRoute('user.create')
            ],
            [
                'name' => __('List') . ' ' . __('Employee'),
                'icon' => 'tabler-password-user',
                'url' => $this->safeRoute('user.index')
            ],
            [
                'name' => __('Update') . ' ' . __('Profile'),
                'icon' => 'tabler-password-user',
                'url' => $this->safeRoute('user.update-profile')
            ],
            [
                'name' => __('Add') . ' ' . __('Attendance'),
                'icon' => 'tabler-clock-24',
                'url' => $this->safeRoute('attendance.create')
            ],
            [
                'name' => __('List') . ' ' . __('Attendance'),
                'icon' => 'tabler-clock-24',
                'url' => $this->safeRoute('attendance.index')
            ],
            [
                'name' => __('Add') . ' ' . __('Salary'),
                'icon' => 'tabler-wallet',
                'url' => $this->safeRoute('salary.create')
            ],
            [
                'name' => __('List') . ' ' . __('Salary'),
                'icon' => 'tabler-wallet',
                'url' => $this->safeRoute('salary.index')
            ]
        ];

            // Filter out items with null URLs
            $navigation = $this->filterNavigationItems($navigation);
            
            return $navigation;
        } catch (\Exception $e) {
            \Log::error('Error building navigation items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get suggestions for popular searches
     */
    private function getSuggestions()
    {
        try {
            $suggestions = [
            'Popular Searches' => [
                [
                    'name' => __('Dashboard'),
                    'icon' => 'tabler-gauge',
                    'url' => url('/dashboard')
                ],
                [
                    'name' => __('POS'),
                    'icon' => 'tabler-shopping-cart-heart',
                    'url' => $this->safeRoute('pos.index')
                ],
                [
                    'name' => __('Item'),
                    'icon' => 'tabler-heart-pin',
                    'url' => $this->safeRoute('item.index')
                ],
                [
                    'name' => __('Customer'),
                    'icon' => 'tabler-user-plus',
                    'url' => $this->safeRoute('customer.index')
                ]
            ],
            'Quick Actions' => [
                [
                    'name' => __('Add') . ' ' . __('Item'),
                    'icon' => 'tabler-heart-pin',
                    'url' => $this->safeRoute('item.create')
                ],
                [
                    'name' => __('Add') . ' ' . __('Sale'),
                    'icon' => 'tabler-shopping-cart-heart',
                    'url' => $this->safeRoute('pos.index')
                ],
                [
                    'name' => __('Add') . ' ' . __('Customer'),
                    'icon' => 'tabler-user-plus',
                    'url' => $this->safeRoute('customer.create')
                ],
                [
                    'name' => __('Add') . ' ' . __('Purchase'),
                    'icon' => 'tabler-basket-down',
                    'url' => $this->safeRoute('purchase.create')
                ]
            ],
            'Reports' => [
                [
                    'name' => __('Sale') . ' ' . __('Report'),
                    'icon' => 'tabler-book-2',
                    'url' => $this->safeRoute('report.sale-report')
                ],
                [
                    'name' => __('Stock') . ' ' . __('Report'),
                    'icon' => 'tabler-book-2',
                    'url' => $this->safeRoute('report.stock-report')
                ],
                [
                    'name' => __('Purchase') . ' ' . __('Report'),
                    'icon' => 'tabler-book-2',
                    'url' => $this->safeRoute('report.purchase-report')
                ],
                [
                    'name' => __('Expense') . ' ' . __('Report'),
                    'icon' => 'tabler-book-2',
                    'url' => $this->safeRoute('report.expense-report')
                ]
            ],
            'Settings' => [
                [
                    'name' => __('All') . ' ' . __('Setting'),
                    'icon' => 'tabler-settings',
                    'url' => $this->safeRoute('setting', 'business_setting')
                ],
                [
                    'name' => __('Update') . ' ' . __('Profile'),
                    'icon' => 'tabler-user',
                    'url' => $this->safeRoute('user.update-profile')
                ]
            ]
        ];
        
        // Filter out items with null URLs
        $filtered = [];
        foreach ($suggestions as $section => $items) {
            $filteredItems = array_filter($items, function($item) {
                return !empty($item['url']);
            });
            if (!empty($filteredItems)) {
                $filtered[$section] = array_values($filteredItems);
            }
        }
        
        return $filtered;
        } catch (\Exception $e) {
            \Log::error('Error building suggestions: ' . $e->getMessage());
            return [];
        }
    }
}
