using System.Collections.Generic;

namespace RashanKiDukan.Views
{
    public class CrudField
    {
        public string Column { get; set; } = "";
        public string Label { get; set; } = "";
        public string Kind { get; set; } = "text";   // text, number, date, time, textarea, check, combo, comboSql
        public string? Sql { get; set; }             // comboSql: SELECT id, name FROM table
        public string ValCol { get; set; } = "Id";
        public string DispCol { get; set; } = "Name";
        public string[]? Options { get; set; }
        public bool Required { get; set; }

        public CrudField(string column, string label, string kind = "text")
        {
            Column = column; Label = label; Kind = kind;
        }
    }

    public class CrudSpec
    {
        public string Title { get; set; } = "";
        public string Plural { get; set; } = "";
        public string Table { get; set; } = "";
        public string[] ListColumns { get; set; } = new string[0];
        public string[] ListHeaders { get; set; } = new string[0];
        public List<CrudField> Fields { get; set; } = new();
        public string OrderBy { get; set; } = "Id DESC";
        public string? RefPrefix { get; set; }
        public string? RefColumn { get; set; }
        public string? FilterWhere { get; set; }
        public string? AfterInsertSql { get; set; }
        public string? AfterUpdateSql { get; set; }
        public bool NumericRef { get; set; }
    }

    public static class EntityRegistry
    {
        private static readonly string[] ActiveInactive = { "Active", "Inactive" };

        public static CrudSpec Outlet() => new()
        {
            Title = "Outlet", Plural = "Outlets", Table = "outlets",
            ListColumns = new[] { "name", "outlet_code", "email", "phone", "active_status" },
            ListHeaders = new[] { "Name", "Code", "Email", "Phone", "Status" },
            OrderBy = "Id DESC",
            RefPrefix = "OUT", RefColumn = "outlet_code",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("outlet_name", "Outlet Name"),
                new("email", "Email"),
                new("phone", "Phone"),
                new("address", "Address", "textarea"),
                new("state_id", "State", "comboSql") { Sql = "SELECT Id, state_name FROM states ORDER BY state_name", DispCol = "state_name" },
                new("active_status", "Status", "combo") { Options = ActiveInactive }
            }
        };

        public static CrudSpec Denomination() => new()
        {
            Title = "Denomination", Plural = "Denominations", Table = "denominations",
            ListColumns = new[] { "name", "value", "type" },
            ListHeaders = new[] { "Name", "Value", "Type" },
            OrderBy = "value DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("value", "Value", "number") { Required = true },
                new("type", "Type", "combo") { Options = new[] { "Notes", "Coins" } }
            }
        };

        public static CrudSpec MultipleCurrency() => new()
        {
            Title = "Multiple Currency", Plural = "Currencies", Table = "multiple_currencies",
            ListColumns = new[] { "name", "symbol", "exchange_rate", "is_base" },
            ListHeaders = new[] { "Name", "Symbol", "Exchange Rate", "Base" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("symbol", "Symbol", "text") { Required = true },
                new("exchange_rate", "Exchange Rate", "number") { Required = true },
                new("is_base", "Base Currency", "check")
            }
        };

        public static CrudSpec Printer() => new()
        {
            Title = "Printer", Plural = "Printers", Table = "printers",
            ListColumns = new[] { "name", "type", "connection_type", "ip_address", "port" },
            ListHeaders = new[] { "Name", "Type", "Connection", "IP Address", "Port" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("type", "Type", "combo") { Options = new[] { "50mm", "80mm", "A4", "A5" } },
                new("connection_type", "Connection Type", "combo") { Options = new[] { "Network", "USB", "Shared" } },
                new("ip_address", "IP Address"),
                new("port", "Port", "number"),
                new("path", "Path / Shared Name")
            }
        };

        public static CrudSpec Counter() => new()
        {
            Title = "Counter", Plural = "Counters", Table = "counters",
            ListColumns = new[] { "name", "outlet_id", "printer_id" },
            ListHeaders = new[] { "Name", "Outlet", "Printer" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("outlet_id", "Outlet", "comboSql") { Sql = "SELECT Id, name FROM outlets", DispCol = "name" },
                new("printer_id", "Printer", "comboSql") { Sql = "SELECT Id, name FROM printers", DispCol = "name" }
            }
        };

        public static CrudSpec DeliveryPartner() => new()
        {
            Title = "Delivery Partner", Plural = "Delivery Partners", Table = "delivery_partners",
            ListColumns = new[] { "name", "phone", "commission_percent" },
            ListHeaders = new[] { "Name", "Phone", "Commission %" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("phone", "Phone"),
                new("address", "Address", "textarea"),
                new("commission_percent", "Commission %", "number")
            }
        };

        public static CrudSpec IncomeCategory() => new()
        {
            Title = "Income Category", Plural = "Income Categories", Table = "income_categories",
            ListColumns = new[] { "name", "description" },
            ListHeaders = new[] { "Name", "Description" },
            OrderBy = "name",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("description", "Description", "textarea")
            }
        };

        public static CrudSpec PaymentMethod() => new()
        {
            Title = "Payment Account", Plural = "Payment Accounts", Table = "payment_methods",
            ListColumns = new[] { "name", "type", "account_type", "status", "sort_id" },
            ListHeaders = new[] { "Name", "Type", "Account Type", "Status", "Sort" },
            OrderBy = "sort_id, Id",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("type", "Type", "combo") { Options = new[] { "Cash", "Bank", "Mobile", "Card", "Other" } },
                new("account_type", "Account Type"),
                new("status", "Status", "combo") { Options = new[] { "Enable", "Disable" } },
                new("sort_id", "Sort Order", "number"),
                new("current_balance", "Current Balance", "number")
            }
        };

        public static CrudSpec Role() => new()
        {
            Title = "Role Permission", Plural = "Roles", Table = "roles",
            ListColumns = new[] { "name" },
            ListHeaders = new[] { "Role Name" },
            OrderBy = "Id",
            Fields = new List<CrudField>
            {
                new("name", "Role Name") { Required = true }
            }
        };

        public static CrudSpec Employee() => new()
        {
            Title = "Employee", Plural = "Employees", Table = "employees",
            ListColumns = new[] { "name", "email", "phone", "role", "salary", "will_login" },
            ListHeaders = new[] { "Name", "Email", "Phone", "Role", "Salary", "Login Allowed" },
            OrderBy = "Id DESC",
            FilterWhere = "del_status='Live'",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("email", "Email"),
                new("phone", "Phone"),
                new("password", "Password", "text"),
                new("role", "Role", "comboSql") { Sql = "SELECT Id, name FROM roles", DispCol = "name" },
                new("salary", "Salary", "number"),
                new("commission", "Commission", "number"),
                new("will_login", "Will Login", "combo") { Options = new[] { "Yes", "No" } },
                new("start_date", "Start Date", "date"),
                new("end_date", "End Date", "date")
            }
        };

        public static CrudSpec Income() => new()
        {
            Title = "Income", Plural = "Incomes", Table = "incomes",
            ListColumns = new[] { "reference_no", "date", "category_id", "payment_method_id", "amount", "note" },
            ListHeaders = new[] { "Reference", "Date", "Category", "Account", "Amount", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "INC", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("category_id", "Category", "comboSql") { Sql = "SELECT Id, name FROM income_categories", DispCol = "name" },
                new("payment_method_id", "Payment Method", "comboSql") { Sql = "SELECT Id, name FROM payment_methods", DispCol = "name" },
                new("amount", "Amount", "number") { Required = true },
                new("employee_id", "Employee", "comboSql") { Sql = "SELECT Id, name FROM employees", DispCol = "name" },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec DepositWithdraw() => new()
        {
            Title = "Deposit/Withdraw", Plural = "Deposit/Withdrawals", Table = "deposit_withdraws",
            ListColumns = new[] { "reference_no", "date", "type", "payment_method_id", "amount", "note" },
            ListHeaders = new[] { "Reference", "Date", "Type", "Account", "Amount", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "DW", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("type", "Type", "combo") { Options = new[] { "Deposit", "Withdraw" } },
                new("payment_method_id", "Payment Method", "comboSql") { Sql = "SELECT Id, name FROM payment_methods", DispCol = "name" },
                new("amount", "Amount", "number") { Required = true },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec CustomerReceive() => new()
        {
            Title = "Customer Receive", Plural = "Customer Receives", Table = "customer_receives",
            ListColumns = new[] { "reference_no", "date", "customer_id", "payment_method_id", "amount", "note" },
            ListHeaders = new[] { "Reference", "Date", "Customer", "Account", "Amount", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "REC", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("payment_method_id", "Payment Method", "comboSql") { Sql = "SELECT Id, name FROM payment_methods", DispCol = "name" },
                new("amount", "Amount", "number") { Required = true },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec Booking() => new()
        {
            Title = "Booking", Plural = "Bookings", Table = "bookings",
            ListColumns = new[] { "customer_id", "item_id", "start_date", "end_date", "status", "note" },
            ListHeaders = new[] { "Customer", "Item", "Start", "End", "Status", "Note" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("item_id", "Item", "comboSql") { Sql = "SELECT Id, name FROM items", DispCol = "name" },
                new("start_date", "Start Date", "date") { Required = true },
                new("end_date", "End Date", "date"),
                new("status", "Status", "combo") { Options = new[] { "Pending", "Confirmed", "Completed", "Cancelled" } },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec Promotion() => new()
        {
            Title = "Promotion", Plural = "Promotions", Table = "promotions",
            ListColumns = new[] { "title", "type", "start_date", "end_date", "status" },
            ListHeaders = new[] { "Title", "Type", "Start Date", "End Date", "Status" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("title", "Title") { Required = true },
                new("type", "Type", "combo") { Options = new[] { "1", "2", "3" } },
                new("discount_type", "Discount Type", "combo") { Options = new[] { "percentage", "flat" } },
                new("discount_value", "Discount Value", "number"),
                new("start_date", "Start Date", "date") { Required = true },
                new("end_date", "End Date", "date") { Required = true },
                new("min_purchase_amount", "Min Purchase", "number"),
                new("max_discount_amount", "Max Discount", "number"),
                new("coupon_code", "Coupon Code"),
                new("status", "Status", "combo") { Options = new[] { "1", "0" } }
            }
        };

        public static CrudSpec Attendance() => new()
        {
            Title = "Attendance", Plural = "Attendance", Table = "attendances",
            ListColumns = new[] { "reference_no", "date", "employee_id", "in_time", "out_time", "note" },
            ListHeaders = new[] { "Reference", "Date", "Employee", "In Time", "Out Time", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "AT", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("employee_id", "Employee", "comboSql") { Sql = "SELECT Id, name FROM employees", DispCol = "name" },
                new("in_time", "In Time", "time"),
                new("out_time", "Out Time", "time"),
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec EmployeeAdvance() => new()
        {
            Title = "Employee Advance Payment", Plural = "Employee Advance Payments", Table = "employee_advance_payments",
            ListColumns = new[] { "reference_no", "date", "employee_id", "amount", "payment_method_id", "note" },
            ListHeaders = new[] { "Reference", "Date", "Employee", "Amount", "Account", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "ADV", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("employee_id", "Employee", "comboSql") { Sql = "SELECT Id, name FROM employees", DispCol = "name" },
                new("amount", "Amount", "number") { Required = true },
                new("payment_method_id", "Payment Method", "comboSql") { Sql = "SELECT Id, name FROM payment_methods", DispCol = "name" },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec Salary() => new()
        {
            Title = "Salary", Plural = "Salaries", Table = "salaries",
            ListColumns = new[] { "reference_no", "year", "month", "generated_date", "total_amount" },
            ListHeaders = new[] { "Reference", "Year", "Month", "Generated", "Total" },
            OrderBy = "Id DESC",
            RefPrefix = "SAL", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("year", "Year", "number") { Required = true },
                new("month", "Month", "number") { Required = true },
                new("generated_date", "Generated Date", "date"),
                new("total_amount", "Total Amount", "number")
            }
        };

        public static CrudSpec PriceList() => new()
        {
            Title = "Price List", Plural = "Price Lists", Table = "price_lists",
            ListColumns = new[] { "name", "description", "customer_type" },
            ListHeaders = new[] { "Name", "Description", "Customer Type" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("description", "Description", "textarea"),
                new("customer_type", "Customer Type", "combo") { Options = new[] { "Retail", "Wholesale", "All" } }
            }
        };

        public static CrudSpec Warranty() => new()
        {
            Title = "Warranty", Plural = "Warranties", Table = "warranties",
            ListColumns = new[] { "reference_no", "customer_id", "item_name", "serial_no", "current_status", "delivery_date" },
            ListHeaders = new[] { "Reference", "Customer", "Item", "Serial No", "Status", "Delivery" },
            OrderBy = "Id DESC",
            RefPrefix = "WAR", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("technician_id", "Technician", "comboSql") { Sql = "SELECT Id, name FROM employees", DispCol = "name" },
                new("item_name", "Item Name"),
                new("item_model", "Item Model"),
                new("serial_no", "Serial No"),
                new("receiving_date", "Receiving Date", "date"),
                new("delivery_date", "Delivery Date", "date"),
                new("current_status", "Status", "combo") { Options = new[] { "Pending", "In Progress", "Completed", "Delivered" } },
                new("description", "Description", "textarea"),
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec Servicing() => new()
        {
            Title = "Servicing", Plural = "Servicings", Table = "servicings",
            ListColumns = new[] { "reference_no", "customer_id", "date", "servicing_charge", "paid_amount", "current_status" },
            ListHeaders = new[] { "Reference", "Customer", "Date", "Charge", "Paid", "Status" },
            OrderBy = "Id DESC",
            RefPrefix = "SER", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("employee_id", "Employee", "comboSql") { Sql = "SELECT Id, name FROM employees", DispCol = "name" },
                new("date", "Date", "date") { Required = true },
                new("receiving_date", "Receiving Date", "date"),
                new("delivery_date", "Delivery Date", "date"),
                new("servicing_charge", "Servicing Charge", "number"),
                new("paid_amount", "Paid Amount", "number"),
                new("payment_method_id", "Payment Method", "comboSql") { Sql = "SELECT Id, name FROM payment_methods", DispCol = "name" },
                new("current_status", "Status", "combo") { Options = new[] { "Pending", "In Progress", "Completed", "Delivered" } },
                new("description", "Description", "textarea"),
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec FixedAssetItem() => new()
        {
            Title = "Fixed Asset Item", Plural = "Fixed Asset Items", Table = "fixed_asset_items",
            ListColumns = new[] { "name", "code", "category", "quantity", "unit_price", "purchase_date" },
            ListHeaders = new[] { "Name", "Code", "Category", "Qty", "Unit Price", "Purchase Date" },
            OrderBy = "Id DESC",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("code", "Code"),
                new("category", "Category"),
                new("description", "Description", "textarea"),
                new("purchase_date", "Purchase Date", "date"),
                new("quantity", "Quantity", "number"),
                new("unit_price", "Unit Price", "number")
            }
        };

        public static CrudSpec InstallmentSale() => new()
        {
            Title = "Installment Sale", Plural = "Installment Sales", Table = "installment_sales",
            ListColumns = new[] { "reference_no", "date", "customer_id", "item_id", "total", "down_payment", "due_amount", "status" },
            ListHeaders = new[] { "Reference", "Date", "Customer", "Item", "Total", "Down Payment", "Due", "Status" },
            OrderBy = "Id DESC",
            RefPrefix = "INS", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("item_id", "Item", "comboSql") { Sql = "SELECT Id, name FROM items", DispCol = "name" },
                new("price", "Price", "number"),
                new("discount_amount", "Discount", "number"),
                new("interest_amount", "Interest Amount", "number"),
                new("shipping_other", "Shipping / Other", "number"),
                new("down_payment", "Down Payment", "number"),
                new("installment_count", "Installment Count", "number"),
                new("status", "Status", "combo") { Options = new[] { "Active", "Completed", "Closed" } },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec SaleReturn() => new()
        {
            Title = "Sale Return", Plural = "Sale Returns", Table = "sale_returns",
            ListColumns = new[] { "reference_no", "date", "customer_id", "sale_id", "total_return_amount", "paid", "due" },
            ListHeaders = new[] { "Reference", "Date", "Customer", "Sale Invoice", "Return Amount", "Paid", "Due" },
            OrderBy = "Id DESC",
            RefPrefix = "SRET", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("sale_id", "Sale Invoice", "comboSql") { Sql = "SELECT Id, invoice_no FROM sales", DispCol = "invoice_no" },
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("total_return_amount", "Return Amount", "number") { Required = true },
                new("paid", "Paid", "number"),
                new("payment_method_id", "Payment Method", "comboSql") { Sql = "SELECT Id, name FROM payment_methods", DispCol = "name" },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec InstallmentCustomer() => new()
        {
            Title = "Installment Customer", Plural = "Installment Customers", Table = "customers",
            ListColumns = new[] { "name", "phone", "gst_number", "opening_balance" },
            ListHeaders = new[] { "Name", "Phone", "GST Number", "Opening Balance" },
            OrderBy = "Id DESC",
            FilterWhere = "is_installment_customer='Yes' AND del_status='Live'",
            Fields = new List<CrudField>
            {
                new("name", "Name") { Required = true },
                new("phone", "Phone"),
                new("email", "Email"),
                new("address", "Address", "textarea"),
                new("state_id", "State", "comboSql") { Sql = "SELECT Id, state_name FROM states ORDER BY state_name", DispCol = "state_name" },
                new("gst_number", "GST Number"),
                new("opening_balance", "Opening Balance", "number"),
                new("opening_balance_type", "Balance Type", "combo") { Options = new[] { "Dr", "Cr" } },
                new("credit_limit", "Credit Limit", "number"),
                new("loyalty_point", "Loyalty Point", "number")
            }
        };

        // ---------------- LINE-ITEM VOUCHERS ----------------

        public static CrudSpec TransferHeader() => new()
        {
            Title = "Transfer", Plural = "Transfers", Table = "transfers",
            ListColumns = new[] { "reference_no", "date", "from_outlet_id", "to_outlet_id", "note", "status" },
            ListHeaders = new[] { "Reference", "Date", "From Outlet", "To Outlet", "Note", "Status" },
            OrderBy = "Id DESC",
            RefPrefix = "TRN", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("from_outlet_id", "From Outlet", "comboSql") { Sql = "SELECT Id, name FROM outlets", DispCol = "name" },
                new("to_outlet_id", "To Outlet", "comboSql") { Sql = "SELECT Id, name FROM outlets", DispCol = "name" },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec DamageHeader() => new()
        {
            Title = "Damage", Plural = "Damages", Table = "damages",
            ListColumns = new[] { "reference_no", "date", "employee_id", "total_loss", "damage_type", "note" },
            ListHeaders = new[] { "Reference", "Date", "Employee", "Total Loss", "Type", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "DMG", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("employee_id", "Employee", "comboSql") { Sql = "SELECT Id, name FROM employees", DispCol = "name" },
                new("damage_type", "Damage Type", "combo") { Options = new[] { "Damaged", "Expired", "Missing" } },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec QuotationHeader() => new()
        {
            Title = "Quotation", Plural = "Quotations", Table = "quotations",
            ListColumns = new[] { "reference_no", "date", "customer_id", "grand_total", "note" },
            ListHeaders = new[] { "Reference", "Date", "Customer", "Grand Total", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "QTN", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("customer_id", "Customer", "comboSql") { Sql = "SELECT Id, name FROM customers", DispCol = "name" },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec FixedAssetStockInHeader() => new()
        {
            Title = "Fixed Asset Stock In", Plural = "Fixed Asset Stock Ins", Table = "fixed_asset_stock_ins",
            ListColumns = new[] { "reference_no", "date", "grand_total", "note" },
            ListHeaders = new[] { "Reference", "Date", "Grand Total", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "FAIN", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("note", "Note", "textarea")
            }
        };

        public static CrudSpec FixedAssetStockOutHeader() => new()
        {
            Title = "Fixed Asset Stock Out", Plural = "Fixed Asset Stock Outs", Table = "fixed_asset_stock_outs",
            ListColumns = new[] { "reference_no", "date", "grand_total", "note" },
            ListHeaders = new[] { "Reference", "Date", "Grand Total", "Note" },
            OrderBy = "Id DESC",
            RefPrefix = "FAOUT", RefColumn = "reference_no",
            Fields = new List<CrudField>
            {
                new("date", "Date", "date") { Required = true },
                new("note", "Note", "textarea")
            }
        };
    }
}
