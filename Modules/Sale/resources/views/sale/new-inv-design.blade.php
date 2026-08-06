<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            font-size: 15px;
            color: #333;
            width: 794px;
            margin: 0 auto !important;
            padding: 30px;
        }
        h1, h2, h3, h4 {
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td:first-child {
            vertical-align: top;
        }
        .invoice-info div {
            display: flex;
            justify-content: center;
        }
        .logo {
            width: 120px;
        }
        
        .section-title {
            background: #f2f2f2;
            padding: 6px;
            font-weight: bold;
            margin-top: 10px;
        }
        .items-table th {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 8px;
            font-weight: bold;
            text-align: left;
        }
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .items-table td.num {
            text-align: right;
        }
        .summary-table tr td:last-child {
            text-align: right;
            width: 120px;
        }
        .grand-total {
            font-size: 14px;
            font-weight: bold;
            background: #f5f5f5;
        }
        .grand-total td {
            padding: 5px;
            border-radius: 2px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }
        .notes {
            margin-top: 10px;
            font-size: 14px;
        }
        .heading-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <table class="header-table">
        <tr>
            <td width="50%" class="company-info">
                <h2 class="heading-title">RASHAN KI DUKAN</h2>
                <p>Outlet: Main Branch</p>
                <p>Phone: +91XXXXXXXXXX</p>
                <p>Email: info@rashankidukan.com</p>
                <p>Address: India</p>
            </td>
            <td width="50%" class="invoice-info" valign="center">
                <div>
                    <img src="logo.png" class="logo">
                </div>
            </td>
        </tr>
    </table>

    <!-- CUSTOMER + SALE INFO -->
    <table style="margin-top:10px">
        <tr>
            <td width="50%" valign="top">
                <div class="heading-title">Customer Information</div>
                <table class="info-table">
                    <tr>
                        <td>Name</td>
                        <td>: John Doe</td>
                    </tr>
                    <tr>
                        <td>Phone</td>
                        <td>: 017XXXXXXXX</td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td>: john@example.com</td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td>: Dhaka, Bangladesh</td>
                    </tr>
                </table>
            </td>
            <td width="50%" valign="top">
                <div class="heading-title">Sale Information</div>
                <table class="info-table">
                    <tr>
                        <td>Invoice No</td>
                        <td>: INV-000234</td>
                    </tr>
                    <tr>
                        <td>Date</td>
                        <td>: 09 Mar 2026</td>
                    </tr>
                    <tr>
                        <td>Time</td>
                        <td>: 10:45 AM</td>
                    </tr>
                    <tr>
                        <td>Sales By</td>
                        <td>: Admin</td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td>: Paid</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ITEMS TABLE -->
    <table class="items-table" style="margin-top:10px">
        <tr>
            <th width="5%">#</th>
            <th width="25%">Item</th>
            <th width="25%">IMEI / Serial</th>
            <th width="8%">Qty</th>
            <th width="12%">Unit Price</th>
            <th width="10%">Discount</th>
            <th width="7%">Tax</th>
            <th width="12%">Total</th>
        </tr>
        <tr>
            <td>1</td>
            <td>iPhone 15 Pro 256GB</td>
            <td>356789123456789</td>
            <td class="num">1</td>
            <td class="num">1200.00</td>
            <td class="num">0.00</td>
            <td class="num">60.00</td>
            <td class="num">1260.00</td>
        </tr>
        <tr>
            <td>2</td>
            <td>Samsung Charger 45W</td>
            <td>-</td>
            <td class="num">2</td>
            <td class="num">25.00</td>
            <td class="num">5.00</td>
            <td class="num">2.50</td>
            <td class="num">47.50</td>
        </tr>
    </table>

    <!-- SUMMARY -->
    <table style="margin-top:10px">
        <tr>
            <td width="60%" valign="top">
                <div class="notes">
                    <strong>Notes:</strong><br>
                    Warranty items cannot be returned without invoice.<br>
                    Mobile phones include 1 year official warranty.
                </div>
            </td>
            <td width="40%" valign="top">
                <table class="summary-table">
                    <tr>
                        <td>Subtotal</td>
                        <td>1275.00</td>
                    </tr>
                    <tr>
                        <td>Item Discount</td>
                        <td>5.00</td>
                    </tr>
                    <tr>
                        <td>Order Discount</td>
                        <td>10.00</td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td>63.50</td>
                    </tr>
                    <tr>
                        <td>Shipping</td>
                        <td>20.00</td>
                    </tr>
                    <tr class="grand-total">
                        <td>Grand Total</td>
                        <td>1343.50</td>
                    </tr>
                    <tr>
                        <td>Paid Amount</td>
                        <td>1343.50</td>
                    </tr>
                    <tr>
                        <td>Due Amount</td>
                        <td>0.00</td>
                    </tr>
                    <tr>
                        <td>Payment Method</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>Cash</td>
                        <td>: 500</td>
                    </tr>
                    <tr>
                        <td>bKash</td>
                        <td>: 500</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        Thank you for shopping with us! <br>
        Software by Door Soft
    </div>
</body>
</html>