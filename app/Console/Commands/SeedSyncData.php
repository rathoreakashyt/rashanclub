<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use Modules\Accounting\Services\ExpenseCategoryService;
use Modules\Accounting\Services\IncomeCategoryService;
use Modules\Accounting\Services\ExpenseService;
use Modules\Accounting\Services\IncomeService;
use Modules\Accounting\Services\DepositWithdrawService;
use Modules\Stock\Services\BrandService;
use Modules\Stock\Services\UnitService;
use Modules\Stock\Services\RackService;
use Modules\Stock\Services\VariationService;
use Modules\Stock\Services\ItemCategoryService;
use Modules\Stock\Services\ItemService;
use Modules\Stock\Services\DamageService;
use Modules\Stock\Services\TransferService;
use Modules\Stock\Services\PriceListService;
use Modules\Sale\Services\CustomerService;
use Modules\Sale\Services\CustomerReceiveService;
use Modules\Sale\Services\PromotionService;
use Modules\Sale\Services\ServicingService;
use Modules\Sale\Services\WarrantyService;
use Modules\Sale\Services\QuotationService;
use Modules\Sale\Services\SaleReturnService;
use Modules\Sale\Services\InstallmentSaleService;
use Modules\Purchase\Services\SupplierService;
use Modules\Purchase\Services\SupplierPaymentService;
use Modules\Purchase\Services\PurchaseService;
use Modules\Purchase\Services\PurchaseReturnService;
use Modules\Configuration\Services\CounterService;
use Modules\Configuration\Services\DenominationService;
use Modules\Configuration\Services\MultipleCurrencyService;
use Modules\Configuration\Services\PrinterService;
use Modules\Configuration\Services\DeliveryPartnerService;
use Modules\Administrator\Services\AttendanceService;
use Modules\Administrator\Services\SalaryService;
use Modules\Administrator\Services\EmployeeAdvancePaymentService;
use Modules\Administrator\Services\UserService;
use Modules\Administrator\Services\RoleService;
use Modules\Sale\Http\Controllers\BookingController;
use Modules\Sale\Http\Controllers\POS\POSController;

use Modules\Purchase\Http\Requests\PurchaseRequest;
use Modules\Purchase\Http\Requests\PurchaseReturnRequest;
use Modules\Sale\Http\Requests\SaleReturnRequest;
use Modules\Administrator\Http\Request\SalaryRequest;
use Modules\Stock\Http\Request\ItemRequest;
use Modules\Administrator\Http\Request\EmployeeAdvancePaymentRequest;

use Modules\Stock\Models\Item;
use Modules\Sale\Models\Sale;

class SeedSyncData extends Command
{
    protected $signature = 'sync:seed-demo {--company=1} {--outlet=1} {--force}';

    protected $description = 'Seed demo data in ALL sync tables through the proper Laravel services (form-based path) so bidirectional sync works. Add/edit/delete flow is exercised end-to-end.';

    protected array $results = [];

    public function handle(): int
    {
        $company = (int) $this->option('company');
        $outlet = (int) $this->option('outlet');

        Session::put('company.company_id', $company);
        Session::put('company.outlet_id', $outlet);
        Session::put('outlet.outlet_id', $outlet);
        Session::put('outlet.id', $outlet);
        Session::put('company.collect_tax', 'No');
        Session::put('company.tax_is_gst', 'No');

        Auth::loginUsingId(1);

        $this->info("Seeding via services (company={$company}, outlet={$outlet}, user=1)...");

        $now = now();
        $ref = fn (string $prefix) => $prefix . '-' . strtoupper($now->format('YmdHis')) . '-' . mt_rand(100, 999);
        $uniq = fn (string $base) => $base . ' ' . $now->format('YmdHis');

        $this->step('Expense Category', function () use ($uniq) {
            return app(ExpenseCategoryService::class)->create(['name' => $uniq('Seed Expense Cat'), 'description' => 'Seeded']);
        });
        $this->step('Income Category', function () use ($uniq) {
            return app(IncomeCategoryService::class)->create(['name' => $uniq('Seed Income Cat'), 'description' => 'Seeded']);
        });
        $this->step('Brand', function () use ($uniq) {
            return app(BrandService::class)->createBrand(['name' => $uniq('Seed Brand'), 'description' => 'Seeded']);
        });
        $this->step('Unit', function () use ($uniq) {
            return app(UnitService::class)->createUnit(['unit_name' => $uniq('Seed Unit'), 'description' => 'Seeded']);
        });
        $this->step('Rack', function () use ($uniq) {
            return app(RackService::class)->createRack(['name' => $uniq('Seed Rack'), 'description' => 'Seeded']);
        });
        $this->step('Variation', function () use ($uniq) {
            return app(VariationService::class)->createVariation(['variation_name' => $uniq('Seed Variation'), 'variation_value' => ['S', 'M', 'L']]);
        });
        $this->step('Item Category', function () use ($uniq) {
            return app(ItemCategoryService::class)->createItemCategory(['name' => $uniq('Seed Category'), 'description' => 'Seeded']);
        });
        $this->step('Denomination', function () {
            return app(DenominationService::class)->createDenomination(['amount' => 500, 'description' => 'Seeded']);
        });
        $this->step('Multiple Currency', function () use ($company) {
            return app(MultipleCurrencyService::class)->createMultipleCurrency(['currency' => 'USD-' . mt_rand(100, 999), 'conversion_rate' => 83.5]);
        });
        $this->step('Printer', function () {
            return app(PrinterService::class)->createPrinter(['title' => 'Seed Printer ' . mt_rand(100, 999), 'invoice_print' => 'web_browser']);
        });
        $this->step('Delivery Partner', function () use ($company) {
            return app(DeliveryPartnerService::class)->createDeliveryPartner(['partner_name' => 'Seed Partner ' . mt_rand(100, 999), 'description' => 'Seeded']);
        });

        $expCat = $incomeCat = $brand = $unit = $rack = $itemCat = $customer = $supplier = $item = null;

        $this->step('Customer', function () use ($ref) {
            return app(CustomerService::class)->createCustomer([
                'name' => 'Seed Customer ' . mt_rand(1000, 9999),
                'phone' => '999' . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => 'seed.customer.' . mt_rand(100, 999) . '@demo.com',
                'address' => 'Demo Address, Nangloi, Delhi',
                'credit_limit' => 5000,
                'discount' => 0,
                'opening_balance' => 0,
                'opening_balance_type' => 'Debit',
                'business_type' => 'B2C',
                'customer_type' => 'Walk',
            ]);
        }, function ($result) use (&$customer) {
            $customer = $result;
        });
        $this->step('Supplier', function () use ($ref) {
            return app(SupplierService::class)->createSupplier([
                'name' => 'Seed Supplier ' . mt_rand(1000, 9999),
                'phone' => '988' . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => 'seed.supplier.' . mt_rand(100, 999) . '@demo.com',
                'address' => 'Demo Supplier Address, Nangloi, Delhi',
                'contact_person' => 'Seed Contact',
                'description' => 'Seeded',
                'opening_balance' => 0,
                'opening_balance_type' => 'Debit',
            ]);
        }, function ($result) use (&$supplier) {
            $supplier = $result;
        });

        $brand = \Modules\Stock\Models\Brand::where('company_id', (int) $this->option('company'))->latest('id')->first();
        $unit = \Modules\Stock\Models\Unit::latest('id')->first();
        $rack = \Modules\Stock\Models\Rack::latest('id')->first();
        $itemCat = \Modules\Stock\Models\ItemCategory::where('company_id', (int) $this->option('company'))->latest('id')->first();

        $this->step('Item', function () use ($itemCat, $brand, $unit, $rack, $supplier, $company, $outlet) {
            $data = [
                'name' => 'Seed Item ' . mt_rand(1000, 9999),
                'code' => 'SEED-' . strtoupper(substr(uniqid(), -6)),
                'alternative_name' => null,
                'category_id' => $itemCat->id,
                'type' => 'General_Product',
                'unit_type' => '1',
                'sale_unit_id' => $unit->id,
                'sale_price' => 100,
                'mrp_price' => 120,
                'purchase_price' => 80,
                'rack_id' => $rack->id,
                'brand_id' => $brand->id,
                'supplier_id' => $supplier->id,
                'alert_quantity' => 5,
                'description' => 'Seeded via service',
                'expiry_date_maintain' => 'No',
                'opening_stock' => 100,
            ];
            $request = new Request();
            $request->replace($data);
            $request->setLaravelSession(app('session.store'));
            return app(ItemService::class)->createItem($data, $request);
        }, function ($result) use (&$item) {
            $item = $result;
        });

        $this->step('Expense', function () use ($ref, $expCat) {
            $cat = \Modules\Accounting\Models\ExpenseCategory::latest('id')->first();
            return app(ExpenseService::class)->create([
                'reference_no' => $ref('EXP'),
                'date' => now()->toDateString(),
                'category_id' => $cat->id,
                'employee_id' => 1,
                'amount' => 500,
                'note' => 'Seeded expense',
                'payment_method_id' => 1,
            ]);
        });
        $this->step('Income', function () use ($ref) {
            $cat = \Modules\Accounting\Models\IncomeCategory::latest('id')->first();
            return app(IncomeService::class)->create([
                'reference_no' => $ref('INC'),
                'date' => now()->toDateString(),
                'category_id' => $cat->id,
                'employee_id' => 1,
                'amount' => 750,
                'note' => 'Seeded income',
                'payment_method_id' => 1,
            ]);
        });
        $this->step('Deposit/Withdraw', function () use ($ref) {
            return app(DepositWithdrawService::class)->createDepositWithdraw([
                'reference_no' => $ref('DEP'),
                'date' => now()->toDateString(),
                'type' => 'Deposit',
                'note' => 'Seeded deposit',
                'amount' => 1000,
                'payment_method_id' => 1,
            ]);
        });

        $this->step('Customer Receive', function () use ($ref, $customer) {
            return app(CustomerReceiveService::class)->createCustomerReceive([
                'reference_no' => $ref('CRV'),
                'date' => now()->toDateString(),
                'customer_id' => $customer->id,
                'amount' => 300,
                'note' => 'Seeded receive',
                'payment_method_id' => 1,
            ]);
        });
        $this->step('Supplier Payment', function () use ($ref, $supplier) {
            return app(SupplierPaymentService::class)->createSupplierPayment([
                'reference_no' => $ref('SPM'),
                'date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'amount' => 400,
                'note' => 'Seeded payment',
                'payment_method_id' => 1,
            ]);
        });
        $this->step('Promotion', function () use ($company, $outlet) {
            return app(PromotionService::class)->createPromotion([
                'type' => 'coupon',
                'title' => 'Seed Promo ' . mt_rand(100, 999),
                'start_date' => now()->addDays(mt_rand(30, 60))->toDateString(),
                'end_date' => now()->addDays(mt_rand(61, 90))->toDateString(),
                'status' => 'active',
                'start_time' => null,
                'end_time' => null,
                'min_purchase_amount' => 100,
                'max_discount_amount' => 50,
                'scheme_basis' => 'bill',
                'bill_level_discount' => 5,
                'bill_level_discount_type' => 'percentage',
                'discount' => 5,
                'coupon_code' => 'SEED' . mt_rand(1000, 9999),
                'applicable_customer_types' => ['Regular'],
            ]);
        });
        $this->step('Servicing', function () use ($customer) {
            return app(ServicingService::class)->createServicing([
                'customer_id' => $customer->id,
                'date' => now()->toDateString(),
                'product_name' => 'Seed Device',
                'product_model' => 'X1',
                'problem_description' => 'Seeded servicing',
                'receiving_date' => now()->toDateString(),
                'delivery_date' => now()->addDays(2)->toDateString(),
                'servicing_charge' => 500,
                'paid_amount' => 500,
                'due_amount' => 0,
                'status' => 'Completed',
                'employee_id' => 1,
                'payment_method_id' => 1,
                'outlet_id' => (int) $this->option('outlet'),
            ]);
        });
        $this->step('Warranty', function () use ($customer) {
            return app(WarrantyService::class)->createWarranty([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_mobile' => $customer->phone,
                'product_name' => 'Seed Product',
                'product_serial_no' => 'SN' . mt_rand(10000, 99999),
                'description' => 'Seeded warranty',
                'receiving_date' => now()->toDateString(),
                'delivery_date' => now()->addDays(2)->toDateString(),
                'current_status' => 'Pending',
                'technician_id' => 1,
                'present_location' => 'Demo Location',
                'sender_service_center' => 'Demo Center',
                'receiver_service_center' => 'Demo Center',
                'outlet_id' => (int) $this->option('outlet'),
            ]);
        });
        $this->step('Quotation', function () use ($customer, $item) {
            return app(QuotationService::class)->createQuotation([
                'date' => now()->toDateString(),
                'reference_no' => 'QT-' . strtoupper(uniqid()),
                'customer_id' => $customer->id,
                'submit_action' => 'print',
                'discount' => 0,
                'grand_total' => 200,
                'note' => 'Seeded quotation',
                'items' => [$item->id],
                'quantity' => [2],
                'unit_price' => [100],
                'total' => [200],
                'description' => ['Seed quotation line'],
            ]);
        });

        $this->step('Booking', function () use ($customer, $outlet) {
            $request = new Request();
            $request->replace([
                'outlet_id' => $outlet,
                'customer_id' => $customer->id,
                'service_seller_id' => null,
                'status' => 'Booked',
                'start_date' => now()->addDays(1)->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'note' => 'Seeded booking',
                'send_email' => false,
            ]);
            $request->setLaravelSession(app('session.store'));
            return app(BookingController::class)->store($request);
        });

        $purchase = null;
        $this->step('Purchase', function () use ($ref, $supplier, $item) {
            $request = new PurchaseRequest();
            $request->replace([
                'reference_no' => $ref('PUR'),
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'supplier_invoice_no' => 'INV-' . mt_rand(1000, 9999),
                'note' => 'Seeded purchase',
                'items' => [$item->id],
                'item_types' => ['General_Product'],
                'quantity' => [10],
                'unit_price' => [80],
                'payments' => [['payment_id' => 1, 'amount' => 800, 'note' => null]],
                'discount' => 0,
            ]);
            $request->setContainer(app());
            $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
            return app(PurchaseService::class)->createPurchase($request);
        }, function ($result) use (&$purchase) {
            $purchase = $result;
        });

        $this->step('Purchase Return', function () use ($ref, $supplier, $item) {
            $request = new PurchaseReturnRequest();
            $request->replace([
                'reference_no' => $ref('PUR-RTN'),
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'purchase_date' => now()->toDateString(),
                'status' => 'taken_by_sup_money_returned',
                'items' => [$item->id],
                'item_types' => ['General_Product'],
                'quantity' => [1],
                'unit_price' => [80],
                'payment_method_id' => 1,
                'total_return_amount' => 80,
                'payment_method_type' => 'cash',
                'note' => 'Seeded purchase return',
            ]);
            $request->setContainer(app());
            $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
            return app(PurchaseReturnService::class)->createPurchaseReturn($request);
        });

        $sale = null;
        $this->step('Sale (POS)', function () use ($customer, $item) {
            $request = new Request();
            $request->replace([
                'customer_id' => $customer->id,
                'employee_id' => null,
                'cart_items' => [[
                    'product_id' => $item->id,
                    'product_type' => 'General_Product',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                ]],
                'subtotal' => 200,
                'tax' => 0,
                'discount' => 0,
                'discount_type' => 'fixed',
                'shipping' => 0,
                'total_payable' => 200,
                'payments' => [['payment_id' => 1, 'amount' => 200, 'gateway_transaction_id' => null, 'gateway_name' => null]],
                'total_paid' => 200,
                'change_amount' => 0,
                'due_amount' => 0,
                'sale_as_due' => false,
            ]);
            $request->setLaravelSession(app('session.store'));
            $response = app(POSController::class)->saveSale($request);
            $payload = json_decode($response->getContent(), true);
            if (($payload['status'] ?? '') !== 'success') {
                throw new \Exception($payload['message'] ?? 'Sale failed');
            }
            return $payload;
        }, function ($result) use (&$sale) {
            $sale = $result;
        });

        $this->step('Sale Return', function () use ($ref, $customer, $item, $sale) {
            $request = new SaleReturnRequest();
            $request->replace([
                'reference_no' => $ref('SRTN'),
                'date' => now()->toDateString(),
                'customer_id' => $customer->id,
                'sale_id' => $sale['sale_id'],
                'items' => [$item->id],
                'item_types' => ['General_Product'],
                'sale_quantities' => [2],
                'return_quantities' => [1],
                'unit_prices_sale' => [100],
                'unit_prices_return' => [100],
                'payment_method_id' => 1,
                'paid' => 100,
                'note' => 'Seeded sale return',
            ]);
            $request->setContainer(app());
            $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
            return app(SaleReturnService::class)->createSaleReturn($request);
        });

        $this->step('Installment Sale', function () use ($customer, $item) {
            return app(InstallmentSaleService::class)->createInstallmentSale([
                'date' => now()->toDateString(),
                'reference_no' => 'INS-' . strtoupper(uniqid()),
                'customer_id' => $customer->id,
                'item_id' => $item->id,
                'item_type' => 'General_Product',
                'price' => 1000,
                'discount' => 0,
                'number_of_installment' => 3,
                'percentage_of_interest' => 0,
                'shipping_other' => 0,
                'total' => 1000,
                'down_payment' => 200,
                'remaining' => 800,
                'installment_type' => 30,
                'payment_method_id' => 1,
                'amount_of_payment' => [400, 400],
                'payment_date' => [now()->addDays(30)->toDateString(), now()->addDays(60)->toDateString()],
                'paid_status' => ['Unpaid', 'Unpaid'],
                'note' => 'Seeded installment sale',
            ]);
        });

        $this->step('Damage', function () use ($item) {
            return app(DamageService::class)->createDamage([
                'date' => now()->toDateString(),
                'reference_no' => 'DMG-' . strtoupper(uniqid()),
                'employee_id' => 1,
                'total_loss' => 80,
                'note' => 'Seeded damage',
                'items' => [$item->id],
                'item_types' => ['General_Product'],
                'last_purchase_price' => [80],
                'damage_quantity' => [1],
                'loss_amount' => [80],
                'total_amount' => [80],
            ]);
        });
        $this->step('Transfer', function () use ($item, $outlet) {
            $toOutlet = $outlet === 1 ? 2 : 1;
            return app(TransferService::class)->createTransfer([
                'date' => now()->toDateString(),
                'reference_no' => 'TRF-' . strtoupper(uniqid()),
                'from_outlet_id' => $outlet,
                'to_outlet_id' => $toOutlet,
                'status' => 'Sent',
                'note_for_sender' => 'Seeded transfer',
                'note_for_receiver' => null,
                'items' => [$item->id],
                'item_types' => ['General_Product'],
                'quantity_amount' => [2],
            ]);
        });
        $this->step('Price List', function () use ($item) {
            return app(PriceListService::class)->createPriceList([
                'name' => 'Seed Price List ' . mt_rand(100, 999),
                'description' => 'Seeded',
                'customer_type' => 'Regular',
                'items' => [['item_id' => $item->id, 'price' => 90]],
            ]);
        });
        $this->step('Attendance', function () {
            return app(AttendanceService::class)->createAttendance([
                'reference_no' => 'ATT-' . strtoupper(uniqid()),
                'date' => now()->toDateString(),
                'employee_id' => 1,
                'in_time' => '09:00',
                'out_time' => '18:00',
                'note' => 'Seeded attendance',
            ]);
        });
        $this->step('Salary', function () {
            $request = new SalaryRequest();
            $request->replace([
                'year' => now()->year,
                'month' => now()->month,
                'generated_date' => now()->toDateString(),
                'total_amount' => 10000,
                'reference_no' => 'SAL-' . strtoupper(uniqid()),
                'items' => [[
                    'employee_id' => 1,
                    'salary_amount' => 10000,
                    'overtime_rate' => 0,
                    'overtime_hour' => 0,
                    'additional_amount' => 0,
                    'deduction_amount' => 0,
                    'absent_day' => 0,
                    'absent_day_amount' => 0,
                    'tips' => 0,
                    'advance_taken' => 0,
                    'net_salary' => 10000,
                    'note' => null,
                ]],
                'payments' => [['payment_method_id' => 1, 'amount' => 10000]],
            ]);
            $request->setContainer(app());
            $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
            $request->setValidator(Validator::make($request->all(), $request->rules()));
            return app(SalaryService::class)->createSalary($request);
        });
        $this->step('Employee Advance', function () {
            $request = new EmployeeAdvancePaymentRequest();
            $request->replace([
                'reference_no' => 'ADV-' . strtoupper(uniqid()),
                'date' => now()->toDateString(),
                'amount' => 500,
                'note' => 'Seeded advance',
                'payment_method_id' => 1,
                'employee_id' => 1,
            ]);
            $request->setContainer(app());
            $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
            return app(EmployeeAdvancePaymentService::class)->createAdvancePayment($request);
        });

        $role = null;
        $this->step('Role', function () {
            return app(RoleService::class)->createRole(['name' => 'Seed Role ' . mt_rand(100, 999), 'permissions' => [1]]);
        }, function ($result) use (&$role) {
            $role = $result;
        });
        $this->step('User', function () use ($role) {
            return app(UserService::class)->createUser([
                'name' => 'Seed Employee ' . mt_rand(100, 999),
                'email' => 'seed.employee.' . mt_rand(100, 999) . '@demo.com',
                'phone' => '987' . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT),
                'role' => $role->id,
                'outlets' => [(int) $this->option('outlet')],
                'salary' => 15000,
                'commission' => 0,
                'will_login' => 'No',
            ]);
        });

        $this->table(['Step', 'Status', 'Detail'], $this->results);

        $failures = collect($this->results)->where('Status', 'FAIL')->count();
        if ($failures > 0) {
            $this->warn("{$failures} step(s) failed. Check the Detail column.");
        } else {
            $this->info('All steps completed successfully. Pull from desktop app to receive the data, then push to verify bidirectional sync.');
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function step(string $label, callable $fn, ?callable $onSuccess = null): void
    {
        try {
            $result = $fn();
            if ($onSuccess) {
                $onSuccess($result);
            }
            $detail = is_object($result) ? get_class($result) : (is_array($result) ? json_encode($result) : (string) $result);
            $this->results[] = [$label, 'OK', mb_substr($detail, 0, 200)];
        } catch (\Throwable $e) {
            $this->results[] = [$label, 'FAIL', mb_substr($e->getMessage(), 0, 200)];
        }
    }
}