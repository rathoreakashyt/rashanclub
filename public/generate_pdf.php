<?php
require __DIR__.'/../vendor/autoload.php';

use Mpdf\Mpdf;

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => [210, 297],
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10,
]);

$html = '
<style>
@page { margin: 0; }
body { font-family: arial, sans-serif; color: #333; margin: 0; padding: 0; }
.cover { width: 190mm; height: 277mm; background: linear-gradient(135deg, #1a237e 0%, #0d47a1 40%, #01579b 100%); color: white; text-align: center; padding-top: 80mm; page-break-after: always; }
.cover h1 { font-size: 42pt; margin-bottom: 5mm; letter-spacing: 2px; }
.cover h2 { font-size: 20pt; font-weight: normal; opacity: 0.9; margin-top: 8mm; }
.cover .tagline { font-size: 14pt; opacity: 0.7; margin-top: 15mm; }
.cover .brand { font-size: 12pt; margin-top: 60mm; opacity: 0.6; }
.cover .line { width: 80mm; height: 2px; background: rgba(255,255,255,0.5); margin: 10mm auto; }

.page { width: 190mm; min-height: 277mm; padding: 10mm 0; page-break-after: always; }
.page:last-child { page-break-after: avoid; }

.section-title { font-size: 22pt; font-weight: bold; color: white; padding: 5mm 8mm; margin: 0 -10mm 6mm -10mm; }
.section-title-blue { background: #1565c0; }
.section-title-green { background: #2e7d32; }
.section-title-orange { background: #e65100; }
.section-title-purple { background: #6a1b9a; }
.section-title-red { background: #c62828; }
.section-title-teal { background: #00695c; }
.section-title-indigo { background: #283593; }
.section-title-pink { background: #ad1457; }
.section-title-brown { background: #4e342e; }
.section-title-cyan { background: #00838f; }
.section-title-deep-purple { background: #4527a0; }

.feature-card { background: #f5f7fa; border-left: 4px solid #1565c0; padding: 3mm 5mm; margin-bottom: 3mm; border-radius: 2px; }
.feature-card h3 { font-size: 11pt; margin: 0 0 1mm 0; color: #1a237e; }
.feature-card p { font-size: 9pt; margin: 0; color: #555; }
.feature-card.green { border-left-color: #2e7d32; }
.feature-card.green h3 { color: #2e7d32; }
.feature-card.orange { border-left-color: #e65100; }
.feature-card.orange h3 { color: #e65100; }
.feature-card.purple { border-left-color: #6a1b9a; }
.feature-card.purple { color: #6a1b9a; }
.feature-card.red { border-left-color: #c62828; }
.feature-card.red h3 { color: #c62828; }
.feature-card.teal { border-left-color: #00695c; }
.feature-card.teal h3 { color: #00695c; }
.feature-card.pink { border-left-color: #ad1457; }
.feature-card.pink h3 { color: #ad1457; }

.stats-row { display: flex; justify-content: space-around; margin: 8mm 0; }
.stat-box { text-align: center; width: 45mm; padding: 5mm; border-radius: 3mm; color: white; }
.stat-box .num { font-size: 28pt; font-weight: bold; }
.stat-box .label { font-size: 9pt; opacity: 0.9; margin-top: 2mm; }
.stat-blue { background: #1565c0; }
.stat-green { background: #2e7d32; }
.stat-orange { background: #e65100; }
.stat-purple { background: #6a1b9a; }

.two-col { display: flex; gap: 5mm; }
.two-col > div { flex: 1; }

.module-header { font-size: 14pt; font-weight: bold; padding: 3mm 5mm; margin-bottom: 4mm; border-radius: 2px; color: white; }
.h-blue { background: #1565c0; }
.h-green { background: #2e7d32; }
.h-orange { background: #e65100; }
.h-purple { background: #6a1b9a; }
.h-red { background: #c62828; }
.h-teal { background: #00695c; }

ul.feat { margin: 0; padding-left: 6mm; }
ul.feat li { font-size: 9.5pt; margin-bottom: 1.5mm; color: #444; }

.tech-badge { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 1.5mm 4mm; border-radius: 2mm; font-size: 8.5pt; margin: 1mm; font-weight: bold; }
.tech-badge.green { background: #e8f5e9; color: #2e7d32; }
.tech-badge.orange { background: #fff3e0; color: #e65100; }
.tech-badge.purple { background: #f3e5f5; color: #6a1b9a; }

.footer-bar { background: #1a237e; color: white; padding: 4mm 8mm; font-size: 9pt; text-align: center; margin-top: 8mm; }
</style>

<!-- PAGE 1: COVER -->
<div class="cover">
    <div class="line"></div>
    <h1>RASHAN KI DUKAN</h1>
    <h2>Complete Point of Sale System</h2>
    <div class="line"></div>
    <div class="tagline">Smart Billing | Real-Time Inventory | Offline POS | Multi-Outlet Management</div>
    <div class="brand">Developed by Digital Akash</div>
</div>

<!-- PAGE 2: OVERVIEW -->
<div class="page">
    <div class="section-title section-title-blue">SYSTEM OVERVIEW</div>
    
    <div class="stats-row">
        <div class="stat-box stat-blue"><div class="num">10+</div><div class="label">Modules</div></div>
        <div class="stat-box stat-green"><div class="num">80+</div><div class="label">Features</div></div>
        <div class="stat-box stat-orange"><div class="num">35+</div><div class="label">Reports</div></div>
        <div class="stat-box stat-purple"><div class="num">80+</div><div class="label">Database Tables</div></div>
    </div>

    <div class="module-header h-blue">What is Rashan Ki Dukan?</div>
    <p style="font-size:10pt; color:#444; line-height:1.6;">
        Rashan Ki Dukan is a powerful, modern Point of Sale (POS) system designed for retail businesses of all sizes. 
        Built on Laravel 11 with MySQL, it provides complete business management from inventory to accounting, 
        with offline POS capability, multi-outlet support, and 35+ detailed reports.
    </p>

    <div class="module-header h-green">Technology Stack</div>
    <div>
        <span class="tech-badge">Laravel 11</span>
        <span class="tech-badge green">MySQL 8</span>
        <span class="tech-badge orange">PHP 8.2</span>
        <span class="tech-badge purple">Bootstrap 5</span>
        <span class="tech-badge">jQuery</span>
        <span class="tech-badge green">IndexedDB (Offline)</span>
        <span class="tech-badge orange">PWA Support</span>
        <span class="tech-badge purple">mPDF (Invoice)</span>
        <span class="tech-badge">ESC/POS (Thermal Printer)</span>
        <span class="tech-badge green">Razorpay Gateway</span>
        <span class="tech-badge orange">Spatie Permissions</span>
        <span class="tech-badge purple">Ziggy (Routes)</span>
        <span class="tech-badge">ZATCA (E-Invoicing)</span>
        <span class="tech-badge green">WhatsApp API</span>
        <span class="tech-badge orange">Email/SMTP</span>
        <span class="tech-badge purple">SMS Gateway</span>
    </div>

    <div class="module-header h-orange" style="margin-top:6mm;">Architecture</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Modular Structure</strong> - 7 independent modules</li>
                <li><strong>Role-Based Access</strong> - Granular permissions per feature</li>
                <li><strong>REST API Ready</strong> - JSON responses for all operations</li>
                <li><strong>Offline-First POS</strong> - IndexedDB + auto sync</li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Multi-Outlet</strong> - Manage multiple store locations</li>
                <li><strong>Session-Based Auth</strong> - Outlet + Company per session</li>
                <li><strong>Blade Templates</strong> - Server-side rendered views</li>
                <li><strong>Docker Ready</strong> - Pre-configured containers</li>
            </ul>
        </div>
    </div>
</div>

<!-- PAGE 3: POS SYSTEM -->
<div class="page">
    <div class="section-title section-title-green">POINT OF SALE (POS)</div>
    
    <div class="two-col">
        <div>
            <div class="feature-card">
                <h3>Touch-Friendly POS Interface</h3>
                <p>Modern, responsive POS screen with product grid, search, category filter, and cart management. Works on desktop, tablet, and mobile.</p>
            </div>
            <div class="feature-card green">
                <h3>Barcode Scanner Support</h3>
                <p>Plug-and-play barcode scanner support. Scan products directly into cart. Manual barcode entry also available.</p>
            </div>
            <div class="feature-card orange">
                <h3>Hold & Resume Bills</h3>
                <p>Hold current sale and start new one. Resume held bills later. Multiple bills can be held simultaneously.</p>
            </div>
            <div class="feature-card purple">
                <h3>Quick Product Search</h3>
                <p>Search by name, barcode, SKU. Filter by category, brand. Real-time stock display on each product card.</p>
            </div>
        </div>
        <div>
            <div class="feature-card red">
                <h3>Multiple Payment Methods</h3>
                <p>Cash, Card, UPI, Bank Transfer, Cheque, Wallet. Split payment across multiple methods in single transaction.</p>
            </div>
            <div class="feature-card teal">
                <h3>Offline POS Mode</h3>
                <p>Works without internet! Sales saved to browser IndexedDB. Auto-sync when connection restored. Never lose a sale.</p>
            </div>
            <div class="feature-card">
                <h3>Tax & Discount Engine</h3>
                <p>Per-item tax, cart-level tax, percentage or fixed discounts. GST/VAT support. Exclusive & Inclusive tax modes.</p>
            </div>
            <div class="feature-card green">
                <h3>Invoice Printing</h3>
                <p>Thermal printer (ESC/POS), A4/A5 PDF invoices via mPDF. Customizable invoice templates with business branding.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-purple" style="margin-top:5mm;">Additional POS Features</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Combo Products</strong> - Bundle items sold as single unit</li>
                <li><strong>Promotions Engine</strong> - Buy X Get Y, flat discounts</li>
                <li><strong>Customer Selection</strong> - Link sales to customers</li>
                <li><strong>Item Seller Tracking</strong> - Track which employee sold what</li>
                <li><strong>Gift Card / Coupon Ready</strong></li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Due Amount Management</strong> - Track partial payments</li>
                <li><strong>Delivery Partner Assignment</strong></li>
                <li><strong>Register Management</strong> - Open/Close cash register</li>
                <li><strong>Denomination Counting</strong> - End-of-day cash count</li>
                <li><strong>Repeat Last Sale</strong> - Quick re-print</li>
            </ul>
        </div>
    </div>
</div>

<!-- PAGE 4: INVENTORY & STOCK -->
<div class="page">
    <div class="section-title section-title-orange">INVENTORY & STOCK MANAGEMENT</div>
    
    <div class="two-col">
        <div>
            <div class="feature-card orange">
                <h3>Product Management</h3>
                <p>Add products with name, SKU, barcode, images, category, brand, unit, variations (size/color), purchase & sale price, tax, warranty info.</p>
            </div>
            <div class="feature-card">
                <h3>Item Variations</h3>
                <p>Define attributes (Color, Size, Material) and create product variations. Each variation has its own stock, price, and barcode.</p>
            </div>
            <div class="feature-card green">
                <h3>Bulk Import / Export</h3>
                <p>Import thousands of products via Excel (CSV). Bulk update prices. Export product list. Opening stock import with IMEI/Serial.</p>
            </div>
            <div class="feature-card purple">
                <h3>Stock Management</h3>
                <p>Real-time stock levels per outlet. Low stock alerts. Stock adjustment. Damage tracking. Stock transfer between outlets.</p>
            </div>
        </div>
        <div>
            <div class="feature-card red">
                <h3>Multi-Outlet Stock</h3>
                <p>Track stock separately for each outlet. Inter-outlet transfer with approval. Centralized stock view across all outlets.</p>
            </div>
            <div class="feature-card teal">
                <h3>Purchase Management</h3>
                <p>Create purchase orders, receive stock, track purchase payments. Purchase return handling. Supplier ledger management.</p>
            </div>
            <div class="feature-card">
                <h3>Damage Management</h3>
                <p>Record damaged/expired items. Remove from stock automatically. Damage reports with reasons and dates.</p>
            </div>
            <div class="feature-card orange">
                <h3>Price History</h3>
                <p>Track all price changes over time. View purchase price history. Sale price history. Cost analysis reports.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-green" style="margin-top:5mm;">Product Configuration</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Categories</strong> - Hierarchical product categories</li>
                <li><strong>Brands</strong> - Brand management with logos</li>
                <li><strong>Units</strong> - Kg, Piece, Box, Liter, etc.</li>
                <li><strong>Racks</strong> - Physical shelf/rack location tracking</li>
                <li><strong>HSN/SAC Codes</strong> - For GST compliance</li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Medicine expiry tracking</strong> - For pharmacy businesses</li>
                <li><strong>IMEI/Serial Number</strong> - For electronics</li>
                <li><strong>Product Images</strong> - Multiple image upload</li>
                <li><strong>Opening Stock</strong> - Set initial quantities</li>
                <li><strong>Fixed Asset Management</strong> - Non-saleable assets</li>
            </ul>
        </div>
    </div>
</div>

<!-- PAGE 5: SALES & CUSTOMER -->
<div class="page">
    <div class="section-title section-title-purple">SALES & CUSTOMER MANAGEMENT</div>
    
    <div class="two-col">
        <div>
            <div class="feature-card purple">
                <h3>Complete Sale Lifecycle</h3>
                <p>Create, view, edit, print, and void sales. Full audit trail. Sale returns with stock restoration. Duplicate invoice detection.</p>
            </div>
            <div class="feature-card">
                <h3>Quotation / Estimate</h3>
                <p>Create professional quotations. Convert quotation to sale with one click. Track quotation status (sent, accepted, rejected).</p>
            </div>
            <div class="feature-card green">
                <h3>Installment Sales</h3>
                <p>Sell products on EMI/Installment. Track down payment, monthly collections, due amounts. Installment schedule management. Customer installment portal.</p>
            </div>
            <div class="feature-card orange">
                <h3>Sale Return</h3>
                <p>Full or partial return. Auto stock restoration. Refund to original payment method. Return reason tracking.</p>
            </div>
        </div>
        <div>
            <div class="feature-card red">
                <h3>Customer Database</h3>
                <p>Complete customer profiles with contact, address, GST number. Customer-wise pricing. Purchase history. Outstanding balance tracking.</p>
            </div>
            <div class="feature-card teal">
                <h3>Customer Receives (Payments)</h3>
                <p>Record customer payments. Partial payments. Payment history. Outstanding balance reports. Multi-payment method support.</p>
            </div>
            <div class="feature-card">
                <h3>Loyalty Points</h3>
                <p>Earn points on purchases. Redeem points as discount. Track available and used loyalty points. Point expiry management.</p>
            </div>
            <div class="feature-card purple">
                <h3>Booking System</h3>
                <p>Pre-book products for customers. Track booking status. Convert booking to sale. Advance payment collection.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-purple" style="margin-top:5mm;">Customer Features</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Customer Groups</strong> - Categorize customers</li>
                <li><strong>Customer-wise Discount</strong> - Special pricing per customer</li>
                <li><strong>WhatsApp Invoice</strong> - Send invoice via WhatsApp</li>
                <li><strong>Email Invoice</strong> - Send invoice via email</li>
                <li><strong>SMS Invoice</strong> - Send invoice via SMS</li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Outstanding Report</strong> - Who owes what</li>
                <li><strong>Customer Ledger</strong> - Complete transaction history</li>
                <li><strong>Customer Balance</strong> - Running balance statement</li>
                <li><strong>Birthday/Anniversary</strong> - Auto marketing triggers</li>
                <li><strong>Due Collection Reminder</strong></li>
            </ul>
        </div>
    </div>
</div>

<!-- PAGE 6: ACCOUNTING -->
<div class="page">
    <div class="section-title section-title-red">ACCOUNTING & FINANCE</div>
    
    <div class="two-col">
        <div>
            <div class="feature-card red">
                <h3>Income Management</h3>
                <p>Record all income sources. Categorize by type (sales, services, rent, etc.). Income reports by period. Income vs expense comparison.</p>
            </div>
            <div class="feature-card">
                <h3>Expense Management</h3>
                <p>Track all business expenses. Expense categories. Recurring expenses. Expense approval workflow. Receipt attachment support.</p>
            </div>
            <div class="feature-card green">
                <h3>Payment Accounts</h3>
                <p>Manage multiple bank/cash accounts. Track balance per account. Account-wise transaction history. Account statement generation.</p>
            </div>
            <div class="feature-card orange">
                <h3>Deposit / Withdraw</h3>
                <p>Transfer between accounts. Deposit cash to bank. Withdraw from bank. Full transaction audit trail.</p>
            </div>
        </div>
        <div>
            <div class="feature-card purple">
                <h3>Supplier Payments</h3>
                <p>Record payments to suppliers. Partial payments. Payment history. Outstanding balance tracking. Supplier ledger.</p>
            </div>
            <div class="feature-card teal">
                <h3>Salary / Payroll</h3>
                <p>Define salary structures. Generate monthly salaries. Deductions and bonuses. Salary payment tracking. Salary slips.</p>
            </div>
            <div class="feature-card red">
                <h3>Employee Advance</h3>
                <p>Track advance payments to employees. Deduct from salary. Advance payment history. Outstanding advance report.</p>
            </div>
            <div class="feature-card">
                <h3>Multi-Currency</h3>
                <p>Support multiple currencies. Currency conversion rates. Foreign currency transactions. Multi-currency reports.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-red" style="margin-top:5mm;">Financial Reports</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Balance Sheet</strong> - Assets vs Liabilities</li>
                <li><strong>Trial Balance</strong> - All account balances</li>
                <li><strong>Cash Flow Report</strong> - Money in vs money out</li>
                <li><strong>Profit & Loss Report</strong></li>
                <li><strong>Account Statement</strong> - Per-account detail</li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Transaction History</strong> - All transactions</li>
                <li><strong>Account Balance</strong> - Current balances</li>
                <li><strong>Income Report</strong> - By period/category</li>
                <li><strong>Expense Report</strong> - By period/category</li>
                <li><strong>Salary Report</strong> - Monthly/yearly</li>
            </ul>
        </div>
    </div>
</div>

<!-- PAGE 7: HRM & REPORTS -->
<div class="page">
    <div class="section-title section-title-teal">HUMAN RESOURCE & REPORTS</div>
    
    <div class="module-header h-teal">Human Resource Management</div>
    <div class="two-col">
        <div>
            <div class="feature-card teal">
                <h3>User & Role Management</h3>
                <p>Create employees with roles (Super Admin, Manager, Cashier, Stock Manager). Granular permissions per feature. 50+ configurable permissions.</p>
            </div>
            <div class="feature-card">
                <h3>Attendance Tracking</h3>
                <p>Mark daily attendance (Present/Absent/Half-Day/Late). Attendance calendar view. Monthly attendance summary. Attendance reports.</p>
            </div>
        </div>
        <div>
            <div class="feature-card green">
                <h3>Salary Management</h3>
                <p>Define salary components (Basic, HRA, Allowance, Deduction). Generate monthly salary. Bulk salary generation. Salary payment tracking.</p>
            </div>
            <div class="feature-card orange">
                <h3>Employee Advance Payment</h3>
                <p>Issue advances to employees. Track outstanding advances. Auto-deduct from salary. Advance history and reports.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-blue" style="margin-top:5mm;">35+ Business Reports</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Register Report</strong> - Daily register summary</li>
                <li><strong>Z Report</strong> - End-of-day cash report</li>
                <li><strong>Daily Summary</strong> - Complete day overview</li>
                <li><strong>Sale Report</strong> - Sales by period/item</li>
                <li><strong>Due Sale Report</strong> - Outstanding dues</li>
                <li><strong>Purchase Report</strong> - Purchase analytics</li>
                <li><strong>Stock Report</strong> - Current stock levels</li>
                <li><strong>Low Stock Alert</strong> - Items below threshold</li>
                <li><strong>Expire Soon Report</strong> - Near-expiry items</li>
                <li><strong>Product Profit Report</strong> - Per-item profitability</li>
                <li><strong>Tax Report / GST Report</strong></li>
                <li><strong>Profit & Loss Report</strong></li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Employee Sale Report</strong> - Per-employee sales</li>
                <li><strong>Product Sale Report</strong> - Best/worst sellers</li>
                <li><strong>Detailed Sale Report</strong> - Line-item detail</li>
                <li><strong>Sale Return Report</strong></li>
                <li><strong>Purchase Return Report</strong></li>
                <li><strong>Damage Report</strong></li>
                <li><strong>Expense Report</strong></li>
                <li><strong>Income Report</strong></li>
                <li><strong>Salary Report</strong></li>
                <li><strong>Attendance Report</strong></li>
                <li><strong>Customer/Supplier Ledger</strong></li>
                <li><strong>Installment Collection/Due Report</strong></li>
                <li><strong>Item Tracking Report</strong></li>
                <li><strong>Price History Report</strong></li>
                <li><strong>Cash Flow Report</strong></li>
                <li><strong>Loyalty Point Reports</strong></li>
            </ul>
        </div>
    </div>
</div>

<!-- PAGE 8: CONFIGURATION & MARKETING -->
<div class="page">
    <div class="section-title section-title-indigo">CONFIGURATION & MARKETING</div>
    
    <div class="module-header h-blue">System Settings</div>
    <div class="two-col">
        <div>
            <div class="feature-card">
                <h3>Business Settings</h3>
                <p>Company name, logo, address, phone, email, GST/VAT number, timezone, currency. White-label customization.</p>
            </div>
            <div class="feature-card green">
                <h3>Invoice Customization</h3>
                <p>Custom invoice templates. Add/remove fields. Business logo on invoice. Terms & conditions. Invoice prefix/numbering.</p>
            </div>
            <div class="feature-card orange">
                <h3>POS Layout Settings</h3>
                <p>Customize POS screen layout. Product card style. Color theme. Sidebar position. Quick access buttons.</p>
            </div>
        </div>
        <div>
            <div class="feature-card purple">
                <h3>Printer Configuration</h3>
                <p>Thermal printer setup (ESC/POS). A4/A5 printer. Receipt width. Auto-cut. Cash drawer kick. Multiple printer support.</p>
            </div>
            <div class="feature-card red">
                <h3>Tax Settings</h3>
                <p>Multiple tax rates. GST slabs (0%, 5%, 12%, 18%, 28%). Tax migration tool. HSN code mapping. Tax inclusive/exclusive.</p>
            </div>
            <div class="feature-card teal">
                <h3>Multi-Outlet Management</h3>
                <p>Create/manage outlets. Outlet-wise stock. Outlet-wise reports. Inter-outlet transfer. Outlet-specific settings.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-purple" style="margin-top:5mm;">Marketing Channels</div>
    <div class="two-col">
        <div>
            <div class="feature-card purple">
                <h3>Email Marketing</h3>
                <p>Birthday greetings, anniversary wishes, custom campaigns. SMTP configured. Email tracking and stats.</p>
            </div>
            <div class="feature-card orange">
                <h3>SMS Marketing</h3>
                <p>Bulk SMS to customers. Transactional SMS. SMS gateway integration. SMS templates. Delivery reports.</p>
            </div>
        </div>
        <div>
            <div class="feature-card green">
                <h3>WhatsApp Marketing</h3>
                <p>Send invoices via WhatsApp. Broadcast messages. Birthday/anniversary auto-messages. WhatsApp Business API integration.</p>
            </div>
            <div class="feature-card red">
                <h3>PWA (Progressive Web App)</h3>
                <p>Installable on mobile. Home screen shortcut. Offline caching. App-like experience. Push notification ready.</p>
            </div>
        </div>
    </div>

    <div class="module-header h-orange" style="margin-top:5mm;">Integrations & Compliance</div>
    <div class="two-col">
        <div>
            <ul class="feat">
                <li><strong>Razorpay Payment Gateway</strong> - Online payments</li>
                <li><strong>Payment Gateway Framework</strong> - Extensible for Stripe, PayPal</li>
                <li><strong>ZATCA E-Invoicing</strong> - Saudi Arabia compliance</li>
                <li><strong>Warranty Tracking</strong> - Product warranty management</li>
            </ul>
        </div>
        <div>
            <ul class="feat">
                <li><strong>Servicing Module</strong> - Repair/service tracking</li>
                <li><strong>Denomination Management</strong> - Cash counting</li>
                <li><strong>Counter Management</strong> - Billing counter setup</li>
                <li><strong>Delivery Partner Management</strong></li>
            </ul>
        </div>
    </div>

    <div class="footer-bar">
        Rashan Ki Dukan - Complete POS Solution | Developed by Digital Akash | contact: 7827307271
    </div>
</div>
';

$mpdf->WriteHTML($html);

// Save to file
$mpdf->Output(__DIR__ . '/../Rashan_Ki_Dukan_Features.pdf', 'F');

echo "PDF generated successfully!\n";
echo "File: C:\\Users\\Akash\\Desktop\\pos\\rashankidukan\\Rashan_Ki_Dukan_Features.pdf\n";
