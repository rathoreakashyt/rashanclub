using System;
using System.Collections.Generic;
using System.Data;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Media;
using Microsoft.Win32;
using RashanKiDukan.Database;

namespace RashanKiDukan.Views
{
    // ─── Combo item for report filter dropdowns ─────────────────────────────
    public class ReportComboItem
    {
        public long Id { get; }
        public string Name { get; }
        public ReportComboItem(long id, string name) { Id = id; Name = name; }
        public override string ToString() => Name;
    }

    // ─── Report specification ───────────────────────────────────────────────
    public class ReportSpec
    {
        public string Title { get; set; } = "";
        public string Sql { get; set; } = "";
        public bool DateFilter { get; set; }
        public string[] FilterDropdowns { get; set; } = Array.Empty<string>();
        public bool WideDates { get; set; }
        public Dictionary<string, string> ColumnHeaders { get; set; } = new();
    }

    // ─── Registry of all reports ────────────────────────────────────────────
    public static class ReportRegistry
    {
        public static readonly Dictionary<string, ReportSpec> All = new();

        static ReportRegistry()
        {
            string Live(string t) => $" AND ({t}.del_status IS NULL OR {t}.del_status='Live')";
            void Add(string key, string title, bool df, string sql, params string[] filters)
                => All[key] = new ReportSpec { Title = title, Sql = sql, DateFilter = df, FilterDropdowns = filters };

            // ── SALES ─────────────────────────────────────────────────────
            Add("register-report", "Register Report", true,
                "SELECT ROW_NUMBER() OVER (ORDER BY date(IFNULL(r.closing_balance_date_time,r.updated_at)) DESC,r.Id DESC) AS sn,"
                + "u.name AS employee,"
                + "IFNULL(r.opening_balance_date_time,r.created_at) AS opening_date_time,"
                + "IFNULL(r.opening_balance,0) AS opening_balance,"
                + "IFNULL(r.sale_paid_amount,0) AS sale_paid_amount,"
                + "IFNULL(r.refund_amount,0) AS sale_return,"
                + "IFNULL(r.customer_due_receive,0) AS customer_receive,"
                + "IFNULL(r.total_purchase,0) AS purchase,"
                + "IFNULL(r.total_purchase_return,0) AS purchase_return,"
                + "IFNULL(r.total_due_payment,0) AS supplier_payment "
                + "FROM registers r LEFT JOIN employees u ON u.Id=r.user_id "
                + "WHERE r.register_status=2 "
                + "AND date(IFNULL(r.closing_balance_date_time,r.updated_at)) BETWEEN @from AND @to"
                + Live("r")
                + " {FILTER:outlet_id on r} ORDER BY date(IFNULL(r.closing_balance_date_time,r.updated_at)) DESC,r.Id DESC",
                "outlet");
            All["register-report"].WideDates = true;

            Add("sale-report", "Sale Report", true,
                "SELECT s.sale_no,s.sale_date,c.name AS customer,"
                + "(SELECT COUNT(*) FROM sale_details sd WHERE sd.sales_id=s.Id AND (sd.del_status IS NULL OR sd.del_status='Live')) AS items,"
                + "IFNULL(s.sub_total,0) AS subtotal,IFNULL(s.vat,0) AS tax,"
                + "IFNULL(s.delivery_charge,0) AS charge,IFNULL(s.total_discount_amount,0) AS discount,"
                + "IFNULL(s.total_payable,0) AS total_payable,IFNULL(s.paid_amount,0) AS paid_amount,"
                + "IFNULL(s.due_amount,0) AS due_amount "
                + "FROM sales s LEFT JOIN customers c ON c.Id=s.customer_id "
                + "WHERE s.sale_date BETWEEN @from AND @to"
                + Live("s")
                + " {FILTER:outlet_id on s} {FILTER:customer_id on s} ORDER BY s.sale_date DESC",
                "outlet", "customer");

            Add("due-sale-report", "Due Sale Report", false,
                "SELECT s.sale_no,s.sale_date,c.name AS customer,"
                + "(SELECT COUNT(*) FROM sale_details sd WHERE sd.sales_id=s.Id AND (sd.del_status IS NULL OR sd.del_status='Live')) AS items,"
                + "IFNULL(s.total_payable,0) AS total_payable,IFNULL(s.paid_amount,0) AS paid_amount,"
                + "IFNULL(s.due_amount,0) AS due_amount "
                + "FROM sales s LEFT JOIN customers c ON c.Id=s.customer_id "
                + "WHERE s.due_amount>0" + Live("s")
                + " {FILTER:customer_id on s} ORDER BY s.sale_date DESC",
                "customer");

            Add("final-invoice-due-report", "Final Invoice Due Report", false,
                "SELECT s.sale_no,s.sale_date,c.name AS customer_name,"
                + "ROUND((IFNULL(s.total_payable,0)-IFNULL((SELECT SUM(total_return_amount) FROM sale_returns sr WHERE sr.sale_id=s.Id AND (sr.del_status IS NULL OR sr.del_status='Live')),0))-IFNULL(s.paid_amount,0),2) AS due "
                + "FROM sales s LEFT JOIN customers c ON c.Id=s.customer_id "
                + "WHERE (IFNULL(s.total_payable,0)-IFNULL((SELECT SUM(total_return_amount) FROM sale_returns sr WHERE sr.sale_id=s.Id AND (sr.del_status IS NULL OR sr.del_status='Live')),0))-IFNULL(s.paid_amount,0)>0"
                + Live("s")
                + " {FILTER:customer_id on s} ORDER BY due DESC",
                "customer");

            Add("service-sale-report", "Service Sale Report", true,
                "SELECT s.sale_no AS invoice_no,s.sale_date,c.name AS customer,i.name AS item,"
                + "sd.qty AS quantity,IFNULL(sd.menu_price_with_discount,sd.menu_unit_price) AS unit_price,"
                + "ROUND(sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price),2) AS total "
                + "FROM sale_details sd "
                + "LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "LEFT JOIN customers c ON c.Id=s.customer_id "
                + "LEFT JOIN items i ON i.Id=sd.item_id "
                + "WHERE i.type='Service_Product' AND s.sale_date BETWEEN @from AND @to "
                + "AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live') "
                + "{FILTER:customer_id on s}",
                "customer");

            Add("combo-service-report", "Combo Service Report", true,
                "SELECT s.sale_no AS invoice_no,s.sale_date,c.name AS customer,"
                + "i.code AS items_code,cs.combo_item_qty AS quantity,"
                + "cs.combo_item_price AS unit_price,"
                + "ROUND(cs.combo_item_qty*cs.combo_item_price,2) AS total "
                + "FROM combo_sales cs "
                + "LEFT JOIN sales s ON s.Id=cs.sale_id "
                + "LEFT JOIN customers c ON c.Id=s.customer_id "
                + "LEFT JOIN items i ON i.Id=cs.combo_item_id "
                + "WHERE s.sale_date BETWEEN @from AND @to "
                + "AND (cs.del_status IS NULL OR cs.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live')");

            Add("employee-sale-report", "Employee Sale Report", true,
                "SELECT s.sale_no,s.sale_date,u.name AS employee,"
                + "IFNULL(s.sub_total,0) AS subtotal,"
                + "IFNULL(u.commission,0) AS commission_percent,"
                + "ROUND(IFNULL(s.sub_total,0)*IFNULL(u.commission,0)/100,2) AS commission_amount "
                + "FROM sales s LEFT JOIN employees u ON u.Id=s.user_id "
                + "WHERE s.sale_date BETWEEN @from AND @to"
                + Live("s")
                + " {FILTER:outlet_id on s} ORDER BY s.sale_date DESC",
                "outlet");

            Add("product-sale-report", "Product Sale Report", true,
                "SELECT s.sale_no AS invoice_no,s.sale_date,c.name AS customer,i.name AS item_product,"
                + "sd.qty AS quantity,IFNULL(sd.menu_price_with_discount,sd.menu_unit_price) AS unit_price,"
                + "ROUND(sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price),2) AS total "
                + "FROM sale_details sd "
                + "LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "LEFT JOIN customers c ON c.Id=s.customer_id "
                + "LEFT JOIN items i ON i.Id=sd.item_id "
                + "WHERE s.sale_date BETWEEN @from AND @to "
                + "AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live') "
                + "{FILTER:outlet_id on s} {FILTER:customer_id on s} {FILTER:item_id on sd} ORDER BY s.sale_date DESC",
                "outlet", "customer");

            Add("product-profit-report", "Product Profit Report", true,
                "SELECT s.sale_no,s.sale_date,sd.qty AS quantity,"
                + "IFNULL(sd.menu_price_with_discount,sd.menu_unit_price) AS sale_unit_price,"
                + "IFNULL(sd.discount_amount,0) AS discount,"
                + "ROUND(sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price),2) AS total_sale,"
                + "IFNULL(i.last_purchase_price,0) AS costing_price,"
                + "ROUND(sd.qty*IFNULL(i.last_purchase_price,0),2) AS total_cost,"
                + "ROUND((sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price))-(sd.qty*IFNULL(i.last_purchase_price,0)),2) AS profit "
                + "FROM sale_details sd "
                + "LEFT JOIN items i ON i.Id=sd.item_id "
                + "LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "WHERE s.sale_date BETWEEN @from AND @to "
                + "AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live') "
                + "{FILTER:outlet_id on s} {FILTER:item_id on sd} ORDER BY s.sale_date DESC,s.Id DESC");

            Add("detailed-sale-report", "Detailed Sale Report", true,
                "SELECT s.sale_no,s.sale_date,c.name AS customer,i.name AS item,"
                + "sd.qty AS quantity,IFNULL(sd.menu_price_with_discount,sd.menu_unit_price) AS price,"
                + "ROUND(sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price),2) AS total,"
                + "IFNULL(s.sub_total,0) AS subtotal,IFNULL(s.total_discount_amount,0) AS discount,"
                + "IFNULL(s.vat,0) AS tax,IFNULL(s.total_payable,0) AS grand_total,"
                + "IFNULL(s.paid_amount,0) AS paid_amount,IFNULL(s.due_amount,0) AS due_amount "
                + "FROM sale_details sd "
                + "LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "LEFT JOIN customers c ON c.Id=s.customer_id "
                + "LEFT JOIN items i ON i.Id=sd.item_id "
                + "WHERE s.sale_date BETWEEN @from AND @to "
                + "AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live') "
                + "{FILTER:outlet_id on s} {FILTER:customer_id on s} ORDER BY s.sale_date DESC,s.Id DESC",
                "outlet", "customer");

            Add("tax-report", "Tax Report", true,
                "SELECT s.sale_no,s.sale_date,IFNULL(s.total_payable,0) AS total_sale,IFNULL(s.vat,0) AS total_tax "
                + "FROM sales s WHERE s.sale_date BETWEEN @from AND @to"
                + Live("s")
                + " {FILTER:outlet_id on s} ORDER BY s.sale_date DESC",
                "outlet");

            Add("gst-report", "GST Report", true,
                "SELECT s.sale_no,s.sale_date,IFNULL(s.total_payable,0) AS taxable,IFNULL(s.vat,0) AS gst_amount "
                + "FROM sales s WHERE s.sale_date BETWEEN @from AND @to"
                + Live("s")
                + " {FILTER:outlet_id on s} ORDER BY s.sale_date DESC",
                "outlet");

            Add("sale-return-report", "Sale Return Report", true,
                "SELECT r.reference_no,r.date,r.created_at AS date_time,c.name AS customer,"
                + "IFNULL(s.sale_no,'-') AS sale_invoice_no,"
                + "(SELECT COUNT(*) FROM sale_return_details srd WHERE srd.sale_return_id=r.Id AND (srd.del_status IS NULL OR srd.del_status='Live')) AS items,"
                + "IFNULL(r.total_return_amount,0) AS amount "
                + "FROM sale_returns r "
                + "LEFT JOIN customers c ON c.Id=r.customer_id "
                + "LEFT JOIN sales s ON s.Id=r.sale_id "
                + "WHERE r.date BETWEEN @from AND @to"
                + Live("r")
                + " {FILTER:customer_id on r} ORDER BY r.date DESC",
                "customer");

            Add("usage-loyalty-point-report", "Usage Loyalty Point Report", true,
                "SELECT sp.date AS payment_date,s.sale_no,c.name AS customer,IFNULL(sp.usage_point,0) AS points_used "
                + "FROM sale_payments sp "
                + "LEFT JOIN sales s ON s.Id=sp.sale_id "
                + "LEFT JOIN customers c ON c.Id=s.customer_id "
                + "WHERE IFNULL(sp.usage_point,0)>0 AND sp.date BETWEEN @from AND @to "
                + "AND (sp.del_status IS NULL OR sp.del_status='Live') "
                + "{FILTER:customer_id on s}");
        }
    }


    // ─── ReportRegistry extension: more reports (added via static initializer trick) ───
    // We use a separate static method to keep the file size manageable.
    public static class ReportRegistryExtra
    {
        public static void Register()
        {
            if (ReportRegistry.All.ContainsKey("purchase-report")) return; // already registered

            string Live(string t) => $" AND ({t}.del_status IS NULL OR {t}.del_status='Live')";
            void Add(string key, string title, bool df, string sql, params string[] filters)
            {
                if (!ReportRegistry.All.ContainsKey(key))
                    ReportRegistry.All[key] = new ReportSpec { Title = title, Sql = sql, DateFilter = df, FilterDropdowns = filters };
            }

            // ── PURCHASE ──────────────────────────────────────────────────
            Add("purchase-report", "Purchase Report", true,
                "SELECT p.reference_no,p.date,p.created_at AS date_time,s.name AS supplier,"
                + "(SELECT COUNT(*) FROM purchase_details pd WHERE pd.purchase_id=p.Id AND (pd.del_status IS NULL OR pd.del_status='Live')) AS items,"
                + "IFNULL(p.grand_total,0) AS grand_total,IFNULL(p.paid,0) AS paid,IFNULL(p.due_amount,0) AS due "
                + "FROM purchases p LEFT JOIN suppliers s ON s.Id=p.supplier_id "
                + "WHERE p.date BETWEEN @from AND @to"
                + Live("p")
                + " {FILTER:supplier_id on p} ORDER BY p.date DESC",
                "supplier");

            Add("purchase-return-report", "Purchase Return Report", true,
                "SELECT r.reference_no,r.date,r.created_at AS date_time,s.name AS supplier,"
                + "(SELECT COUNT(*) FROM purchase_return_details prd WHERE prd.pur_return_id=r.Id AND (prd.del_status IS NULL OR prd.del_status='Live')) AS items,"
                + "IFNULL(r.total_return_amount,0) AS amount "
                + "FROM purchase_returns r LEFT JOIN suppliers s ON s.Id=r.supplier_id "
                + "WHERE r.date BETWEEN @from AND @to"
                + Live("r")
                + " {FILTER:supplier_id on r} ORDER BY r.date DESC",
                "supplier");

            Add("expense-report", "Expense Report", true,
                "SELECT e.reference_no,e.date,c.name AS category,u.name AS responsible_person,"
                + "IFNULL(e.amount,0) AS amount,IFNULL(e.note,'') AS note "
                + "FROM expenses e "
                + "LEFT JOIN expense_categories c ON c.Id=e.category_id "
                + "LEFT JOIN employees u ON u.Id=e.employee_id "
                + "WHERE e.date BETWEEN @from AND @to"
                + Live("e")
                + " ORDER BY e.date DESC");

            Add("income-report", "Income Report", true,
                "SELECT i.reference_no,i.date,c.name AS category,u.name AS responsible_person,"
                + "IFNULL(i.amount,0) AS amount,IFNULL(i.note,'') AS note "
                + "FROM incomes i "
                + "LEFT JOIN income_categories c ON c.Id=i.category_id "
                + "LEFT JOIN employees u ON u.Id=i.employee_id "
                + "WHERE i.date BETWEEN @from AND @to"
                + Live("i")
                + " ORDER BY i.date DESC");

            Add("salary-report", "Salary Report", true,
                "SELECT s.reference_no,s.year,s.month,s.generated_date,IFNULL(s.total_amount,0) AS amount "
                + "FROM salaries s WHERE s.generated_date BETWEEN @from AND @to"
                + Live("s")
                + " ORDER BY s.generated_date DESC");

            Add("damage-report", "Damage Report", true,
                "SELECT d.reference_no,d.date,d.created_at AS date_time,u.name AS responsible_person,"
                + "(SELECT COUNT(*) FROM damage_details dd WHERE dd.damage_id=d.Id AND (dd.del_status IS NULL OR dd.del_status='Live')) AS items,"
                + "IFNULL(d.total_loss,0) AS total_loss "
                + "FROM damages d LEFT JOIN employees u ON u.Id=d.employee_id "
                + "WHERE d.date BETWEEN @from AND @to"
                + Live("d")
                + " ORDER BY d.date DESC");

            Add("supplier-ledger-report", "Supplier Ledger Report", true,
                "SELECT p.date,p.reference_no,'Purchase' AS type,s.name AS supplier,"
                + "IFNULL(p.grand_total,0) AS debit,0 AS credit "
                + "FROM purchases p LEFT JOIN suppliers s ON s.Id=p.supplier_id "
                + "WHERE p.date BETWEEN @from AND @to AND (p.del_status IS NULL OR p.del_status='Live') "
                + "{FILTER:supplier_id on p} "
                + "UNION ALL "
                + "SELECT sp.date,sp.reference_no,'Payment',s2.name,0,IFNULL(sp.amount,0) "
                + "FROM supplier_payments sp LEFT JOIN suppliers s2 ON s2.Id=sp.supplier_id "
                + "WHERE sp.date BETWEEN @from AND @to AND (sp.del_status IS NULL OR sp.del_status='Live') "
                + "{FILTER:supplier_id on sp} "
                + "UNION ALL "
                + "SELECT pr.date,pr.reference_no,'Return',s3.name,0,IFNULL(pr.total_return_amount,0) "
                + "FROM purchase_returns pr LEFT JOIN suppliers s3 ON s3.Id=pr.supplier_id "
                + "WHERE pr.date BETWEEN @from AND @to AND (pr.del_status IS NULL OR pr.del_status='Live') "
                + "{FILTER:supplier_id on pr} "
                + "ORDER BY date",
                "supplier");

            Add("supplier-balance-report", "Supplier Balance Report", false,
                "SELECT s.name AS supplier,"
                + "ROUND(IFNULL(s.opening_balance,0)*CASE WHEN IFNULL(s.opening_balance_type,'') IN ('Dr','Debit') THEN -1 ELSE 1 END"
                + "+(SELECT IFNULL(SUM(grand_total),0) FROM purchases WHERE supplier_id=s.Id AND (del_status IS NULL OR del_status='Live'))"
                + "-(SELECT IFNULL(SUM(amount),0) FROM supplier_payments WHERE supplier_id=s.Id AND (del_status IS NULL OR del_status='Live'))"
                + "-(SELECT IFNULL(SUM(total_return_amount),0) FROM purchase_returns WHERE supplier_id=s.Id AND (del_status IS NULL OR del_status='Live')),2) AS current_balance "
                + "FROM suppliers s WHERE 1=1"
                + Live("s")
                + " ORDER BY current_balance DESC");

            // ── STOCK ─────────────────────────────────────────────────────
            Add("stock-report", "Stock Report", false,
                "SELECT i.code,i.name AS item_name,ic.name AS category,"
                + "(SELECT IFNULL(SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END),0) FROM view_stock_detail v WHERE v.item_id=i.Id AND (v.del_status IS NULL OR v.del_status='Live')) AS stock_qty,"
                + "IFNULL(i.last_purchase_price,0) AS lpp,"
                + "ROUND((SELECT IFNULL(SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END),0) FROM view_stock_detail v WHERE v.item_id=i.Id AND (v.del_status IS NULL OR v.del_status='Live'))*IFNULL(i.last_purchase_price,0),2) AS total "
                + "FROM items i LEFT JOIN item_categories ic ON ic.Id=i.category_id "
                + "WHERE 1=1"
                + Live("i")
                + " {FILTER:category_id on i} {FILTER:supplier_id on i} ORDER BY i.code",
                "category", "supplier");

            Add("low-stock-report", "Low Stock Report", false,
                "SELECT i.code,i.name AS item_name,"
                + "(SELECT IFNULL(SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END),0) FROM view_stock_detail v WHERE v.item_id=i.Id AND (v.del_status IS NULL OR v.del_status='Live')) AS stock_qty,"
                + "IFNULL(i.alert_quantity,0) AS alert_qty "
                + "FROM items i "
                + "WHERE (SELECT IFNULL(SUM(CASE WHEN v.type=1 THEN v.stock_quantity ELSE -v.stock_quantity END),0) FROM view_stock_detail v WHERE v.item_id=i.Id AND (v.del_status IS NULL OR v.del_status='Live'))<=IFNULL(i.alert_quantity,0)"
                + Live("i")
                + " {FILTER:category_id on i} ORDER BY i.code",
                "category");

            Add("expire-soon-report", "Expire Soon Report", false,
                "SELECT i.name,i.code,IFNULL(i.warranty_date,'') AS warranty_date,"
                + "IFNULL(i.stock_quantity,0) AS stock_qty "
                + "FROM items i "
                + "WHERE i.warranty_date IS NOT NULL AND i.warranty_date!='' AND i.warranty_date<=date('now','+30 day')"
                + Live("i"));

            Add("item-tracking-report", "Item Tracking Report", true,
                "SELECT p.date,i.name AS item,'Purchase' AS type,p.reference_no,"
                + "pd.quantity_amount AS qty_in,0 AS qty_out,pd.unit_price "
                + "FROM purchase_details pd "
                + "LEFT JOIN items i ON i.Id=pd.item_id "
                + "LEFT JOIN purchases p ON p.Id=pd.purchase_id "
                + "WHERE p.date BETWEEN @from AND @to "
                + "AND (pd.del_status IS NULL OR pd.del_status='Live') AND (p.del_status IS NULL OR p.del_status='Live') "
                + "UNION ALL "
                + "SELECT s.sale_date,i2.name,'Sale',s.sale_no,0,sd.qty,"
                + "IFNULL(sd.menu_price_with_discount,sd.menu_unit_price) "
                + "FROM sale_details sd "
                + "LEFT JOIN items i2 ON i2.Id=sd.item_id "
                + "LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "WHERE s.sale_date BETWEEN @from AND @to "
                + "AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live') "
                + "ORDER BY date");

            Add("price-history-report", "Price History Report", true,
                "SELECT p.date,i.name AS item,'Purchase' AS type,p.reference_no,"
                + "pd.quantity_amount AS quantity,pd.unit_price,"
                + "ROUND(pd.quantity_amount*pd.unit_price,2) AS total_amount "
                + "FROM purchase_details pd "
                + "LEFT JOIN items i ON i.Id=pd.item_id "
                + "LEFT JOIN purchases p ON p.Id=pd.purchase_id "
                + "WHERE p.date BETWEEN @from AND @to "
                + "AND (pd.del_status IS NULL OR pd.del_status='Live') AND (p.del_status IS NULL OR p.del_status='Live') "
                + "UNION ALL "
                + "SELECT s.sale_date,i2.name,'Sale',s.sale_no,sd.qty,"
                + "IFNULL(sd.menu_price_with_discount,sd.menu_unit_price),"
                + "ROUND(sd.qty*IFNULL(sd.menu_price_with_discount,sd.menu_unit_price),2) "
                + "FROM sale_details sd "
                + "LEFT JOIN items i2 ON i2.Id=sd.item_id "
                + "LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "WHERE s.sale_date BETWEEN @from AND @to "
                + "AND (sd.del_status IS NULL OR sd.del_status='Live') AND (s.del_status IS NULL OR s.del_status='Live') "
                + "ORDER BY date");

            Add("warranty-checking-report", "Warranty Checking Report", false,
                "SELECT w.reference_no,w.item_name,w.serial_no,w.current_status,w.delivery_date "
                + "FROM warranties w ORDER BY w.Id DESC");

            Add("installment-report", "Installment Report", true,
                "SELECT isl.reference_no AS invoice_no,isl.date,c.name AS customer,"
                + "i.name AS product,isd.amount AS amount_of_installment,"
                + "isd.payment_date AS installment_date,IFNULL(isd.paid_amount,0) AS paid_amount,"
                + "IFNULL(isd.paid_date,'') AS paid_date,isd.paid_status "
                + "FROM installment_sale_details isd "
                + "LEFT JOIN installment_sales isl ON isl.Id=isd.installment_sale_id "
                + "LEFT JOIN customers c ON c.Id=isl.customer_id "
                + "LEFT JOIN items i ON i.Id=isl.item_id "
                + "WHERE isl.date BETWEEN @from AND @to "
                + "AND (isd.del_status IS NULL OR isd.del_status='Live') AND (isl.del_status IS NULL OR isl.del_status='Live') "
                + "{FILTER:customer_id on isl} ORDER BY isd.payment_date",
                "customer");

            Add("installment-due-report", "Installment Due Report", false,
                "SELECT isl.reference_no AS invoice_no,isl.date AS sale_date,"
                + "c.name AS customer,i.name AS product,"
                + "IFNULL(isl.price,0) AS price,IFNULL(isl.total,0) AS total,"
                + "IFNULL(isl.down_payment,0) AS down_payment,"
                + "IFNULL((SELECT SUM(isd2.paid_amount) FROM installment_sale_details isd2 WHERE isd2.installment_sale_id=isl.Id),0) AS total_paid,"
                + "ROUND(IFNULL((SELECT SUM(isd4.amount) FROM installment_sale_details isd4 WHERE isd4.installment_sale_id=isl.Id),0)"
                + "-IFNULL((SELECT SUM(isd5.paid_amount) FROM installment_sale_details isd5 WHERE isd5.installment_sale_id=isl.Id),0),2) AS current_due "
                + "FROM installment_sales isl "
                + "LEFT JOIN customers c ON c.Id=isl.customer_id "
                + "LEFT JOIN items i ON i.Id=isl.item_id "
                + "WHERE EXISTS (SELECT 1 FROM installment_sale_details isd WHERE isd.installment_sale_id=isl.Id AND isd.paid_status IN ('Unpaid','Partial'))"
                + Live("isl")
                + " {FILTER:customer_id on isl} ORDER BY isl.date DESC",
                "customer");

            // ── PARTY ─────────────────────────────────────────────────────
            Add("customer-ledger-report", "Customer Ledger Report", true,
                "SELECT s.sale_date AS date,s.sale_no AS reference_no,'Sale' AS type,"
                + "c.name AS customer,IFNULL(s.total_payable,0) AS debit,0 AS credit "
                + "FROM sales s LEFT JOIN customers c ON c.Id=s.customer_id "
                + "WHERE s.sale_date BETWEEN @from AND @to AND (s.del_status IS NULL OR s.del_status='Live') "
                + "{FILTER:customer_id on s} {FILTER:outlet_id on s} "
                + "UNION ALL "
                + "SELECT cr.date,cr.reference_no,'Receive',c2.name,0,IFNULL(cr.amount,0) "
                + "FROM customer_receives cr LEFT JOIN customers c2 ON c2.Id=cr.customer_id "
                + "WHERE cr.date BETWEEN @from AND @to AND (cr.del_status IS NULL OR cr.del_status='Live') "
                + "{FILTER:customer_id on cr} {FILTER:outlet_id on cr} "
                + "UNION ALL "
                + "SELECT sr.date,sr.reference_no,'Sale Return',c3.name,0,IFNULL(sr.total_return_amount,0) "
                + "FROM sale_returns sr LEFT JOIN customers c3 ON c3.Id=sr.customer_id "
                + "WHERE sr.date BETWEEN @from AND @to AND (sr.del_status IS NULL OR sr.del_status='Live') "
                + "{FILTER:customer_id on sr} {FILTER:outlet_id on sr} "
                + "ORDER BY date",
                "customer", "outlet");

            Add("customer-balance-report", "Customer Balance Report", false,
                "SELECT c.name AS customer,"
                + "ROUND(IFNULL(c.opening_balance,0)*CASE WHEN IFNULL(c.opening_balance_type,'') IN ('Dr','Debit') THEN 1 ELSE -1 END"
                + "+(SELECT IFNULL(SUM(total_payable),0) FROM sales WHERE customer_id=c.Id AND (del_status IS NULL OR del_status='Live'))"
                + "-(SELECT IFNULL(SUM(amount),0) FROM customer_receives WHERE customer_id=c.Id AND (del_status IS NULL OR del_status='Live'))"
                + "-(SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE customer_id=c.Id AND (del_status IS NULL OR del_status='Live')),2) AS current_balance "
                + "FROM customers c WHERE 1=1"
                + Live("c")
                + " ORDER BY current_balance DESC");

            Add("customer-receive-report", "Customer Receive Report", true,
                "SELECT r.reference_no,r.date,c.name AS customer,"
                + "IFNULL(r.amount,0) AS amount,IFNULL(r.note,'') AS note "
                + "FROM customer_receives r LEFT JOIN customers c ON c.Id=r.customer_id "
                + "WHERE r.date BETWEEN @from AND @to"
                + Live("r")
                + " {FILTER:customer_id on r} {FILTER:outlet_id on r} ORDER BY r.date DESC",
                "customer", "outlet");

            Add("available-loyalty-point-report", "Available Loyalty Point Report", false,
                "SELECT name AS customer,IFNULL(loyalty_point,0) AS loyalty_points "
                + "FROM customers WHERE IFNULL(loyalty_point,0)>0"
                + Live("customers")
                + " ORDER BY loyalty_points DESC");

            Add("scheme-report", "Scheme / Promotion Report", false,
                "SELECT IFNULL(p.title,'') AS title,IFNULL(p.type,'') AS type,"
                + "IFNULL(p.start_date,'') AS start_date,IFNULL(p.end_date,'') AS end_date,"
                + "IFNULL(p.min_purchase_amount,0) AS min_purchase,"
                + "IFNULL(p.discount_value,0) AS discount,IFNULL(p.status,'') AS status "
                + "FROM promotions p WHERE 1=1"
                + Live("p")
                + " ORDER BY p.Id DESC");

            Add("servicing-report", "Servicing Report", true,
                "SELECT s.reference_no,s.date,s.delivery_date,c.name AS customer,"
                + "IFNULL(s.servicing_charge,0) AS servicing_charge,"
                + "IFNULL(s.paid_amount,0) AS paid_amount,IFNULL(s.due_amount,0) AS due_amount,"
                + "IFNULL(s.current_status,'') AS current_status "
                + "FROM servicings s LEFT JOIN customers c ON c.Id=s.customer_id "
                + "WHERE s.date BETWEEN @from AND @to"
                + Live("s")
                + " {FILTER:customer_id on s} ORDER BY s.date DESC",
                "customer");

            // ── ACCOUNTING ────────────────────────────────────────────────
            Add("profit-loss-report", "Profit / Loss Report", true,
                "SELECT 'Total Sales' AS description,IFNULL(SUM(total_payable),0) AS amount "
                + "FROM sales WHERE sale_date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Cost of Sale',"
                + "IFNULL(SUM(sd.qty*IFNULL(i.last_purchase_price,0)),0) "
                + "FROM sale_details sd LEFT JOIN items i ON i.Id=sd.item_id LEFT JOIN sales s ON s.Id=sd.sales_id "
                + "WHERE s.sale_date BETWEEN @from AND @to AND (sd.del_status IS NULL OR sd.del_status='Live') "
                + "UNION ALL SELECT 'Tax (VAT)',IFNULL(SUM(vat),0) FROM sales WHERE sale_date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Delivery Charge',IFNULL(SUM(delivery_charge),0) FROM sales WHERE sale_date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Discount',IFNULL(SUM(total_discount_amount),0) FROM sales WHERE sale_date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Other Income',IFNULL(SUM(amount),0) FROM incomes WHERE date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Sale Return',IFNULL(SUM(total_return_amount),0) FROM sale_returns WHERE date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Servicing',IFNULL(SUM(servicing_charge),0) FROM servicings WHERE date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Salaries',IFNULL(SUM(total_amount),0) FROM salaries WHERE generated_date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Expenses',IFNULL(SUM(amount),0) FROM expenses WHERE date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live')");

            Add("cash-flow-report", "Cash Flow Report", true,
                "SELECT s.sale_date AS date,'Sale' AS type,s.sale_no AS reference_no,"
                + "IFNULL(s.total_payable,0) AS credit,0 AS debit "
                + "FROM sales s WHERE s.sale_date BETWEEN @from AND @to AND (s.del_status IS NULL OR s.del_status='Live') "
                + "UNION ALL SELECT p.date,'Purchase',p.reference_no,0,IFNULL(p.grand_total,0) "
                + "FROM purchases p WHERE p.date BETWEEN @from AND @to AND (p.del_status IS NULL OR p.del_status='Live') "
                + "UNION ALL SELECT pr.date,'Purchase Return',pr.reference_no,IFNULL(pr.total_return_amount,0),0 "
                + "FROM purchase_returns pr WHERE pr.date BETWEEN @from AND @to AND (pr.del_status IS NULL OR pr.del_status='Live') "
                + "UNION ALL SELECT sr.date,'Sale Return',sr.reference_no,0,IFNULL(sr.total_return_amount,0) "
                + "FROM sale_returns sr WHERE sr.date BETWEEN @from AND @to AND (sr.del_status IS NULL OR sr.del_status='Live') "
                + "UNION ALL SELECT i.date,'Income',i.reference_no,IFNULL(i.amount,0),0 "
                + "FROM incomes i WHERE i.date BETWEEN @from AND @to AND (i.del_status IS NULL OR i.del_status='Live') "
                + "UNION ALL SELECT e.date,'Expense',e.reference_no,0,IFNULL(e.amount,0) "
                + "FROM expenses e WHERE e.date BETWEEN @from AND @to AND (e.del_status IS NULL OR e.del_status='Live') "
                + "ORDER BY date");

            Add("account-balance-report", "Account Balance Report", false,
                "SELECT name AS account,type,IFNULL(current_balance,0) AS balance,status "
                + "FROM payment_methods ORDER BY sort_id,Id");

            Add("account-statement-report", "Account Statement Report", true,
                "SELECT pm.name AS account,e.date,e.reference_no,'Expense' AS type,e.amount "
                + "FROM expenses e LEFT JOIN payment_methods pm ON pm.Id=e.payment_method_id "
                + "WHERE e.date BETWEEN @from AND @to "
                + "UNION ALL "
                + "SELECT pm2.name,i.date,i.reference_no,'Income',i.amount "
                + "FROM incomes i LEFT JOIN payment_methods pm2 ON pm2.Id=i.payment_method_id "
                + "WHERE i.date BETWEEN @from AND @to ORDER BY date");

            Add("balance-sheet-report", "Balance Sheet Report", false,
                "SELECT 'Assets - Stock Value' AS account,"
                + "IFNULL(SUM(stock_quantity*purchase_price),0) AS amount FROM items "
                + "UNION ALL SELECT 'Liabilities - Supplier Due',IFNULL(SUM(due_amount),0) FROM purchases "
                + "UNION ALL SELECT 'Assets - Customer Due',IFNULL(SUM(due_amount),0) FROM sales");

            Add("trial-balance-report", "Trial Balance Report", false,
                "SELECT 'Sales' AS account,'Credit' AS side,IFNULL(SUM(grand_total),0) AS amount FROM sales "
                + "UNION ALL SELECT 'Purchases','Debit',IFNULL(SUM(grand_total),0) FROM purchases "
                + "UNION ALL SELECT 'Expenses','Debit',IFNULL(SUM(amount),0) FROM expenses "
                + "UNION ALL SELECT 'Other Income','Credit',IFNULL(SUM(amount),0) FROM incomes");

            Add("transaction-history-report", "Transaction History Report", true,
                "SELECT 'Sale' AS type,sale_date AS date,sale_no AS reference,total_payable AS amount "
                + "FROM sales WHERE sale_date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Purchase',date,reference_no,grand_total "
                + "FROM purchases WHERE date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "UNION ALL SELECT 'Expense',date,reference_no,amount "
                + "FROM expenses WHERE date BETWEEN @from AND @to AND (del_status IS NULL OR del_status='Live') "
                + "ORDER BY date DESC");

            // ── HR ────────────────────────────────────────────────────────
            Add("attendance-report", "Attendance Report", true,
                "SELECT a.reference_no,a.date,u.name AS employee,"
                + "IFNULL(a.in_time,'') AS in_time,IFNULL(a.out_time,'') AS out_time "
                + "FROM attendances a LEFT JOIN employees u ON u.Id=a.employee_id "
                + "WHERE a.date BETWEEN @from AND @to"
                + Live("a")
                + " ORDER BY a.date DESC");
        }
    }


    // ─── Main ReportPage UserControl ────────────────────────────────────────
    public partial class ReportPage : UserControl
    {
        private readonly MainDashboard? _dashboard;
        private readonly ReportSpec _spec;
        private readonly DatabaseService _db = new();
        private DataTable? _currentTable;

        public ReportPage(MainDashboard? dashboard, string key)
        {
            ReportRegistryExtra.Register();
            InitializeComponent();
            ReportPdfHelper.Attach(exportPopup, () => dataGrid.ItemsSource as DataView, _spec.Title);
            _dashboard = dashboard;

            if (!ReportRegistry.All.TryGetValue(key, out _spec!))
                _spec = new ReportSpec { Title = key, Sql = "SELECT 'No data' AS info" };

            lblTitle.Text = _spec.Title;
            lblBreadcrumb.Text = _spec.Title;
            lblCardTitle.Text = _spec.Title;

            // Date defaults
            if (_spec.DateFilter)
            {
                if (_spec.WideDates)
                { dpFrom.SelectedDate = new DateTime(2000, 1, 1); dpTo.SelectedDate = DateTime.Today; }
                else
                { dpFrom.SelectedDate = new DateTime(DateTime.Today.Year, DateTime.Today.Month, 1); dpTo.SelectedDate = DateTime.Today; }
            }
            else
            {
                pnlDateFrom.Visibility = Visibility.Collapsed;
                pnlDateTo.Visibility = Visibility.Collapsed;
            }

            SetupDropdowns();
            LoadData();
        }

        // ── Setup filter dropdowns ──────────────────────────────────────────
        private void SetupDropdowns()
        {
            var f = _spec.FilterDropdowns;
            pnlOutlet.Visibility   = (f.Contains("outlet")   || f.Contains("category")) ? Visibility.Visible : Visibility.Collapsed;
            pnlCustomer.Visibility = f.Contains("customer") ? Visibility.Visible : Visibility.Collapsed;
            pnlSupplier.Visibility = f.Contains("supplier") ? Visibility.Visible : Visibility.Collapsed;
            pnlEmployee.Visibility = f.Contains("employee") ? Visibility.Visible : Visibility.Collapsed;
            pnlItem.Visibility     = f.Contains("item")     ? Visibility.Visible : Visibility.Collapsed;

            if (f.Contains("category"))
            {
                lblOutletLabel.Text = "Group";
                LoadCombo(cmbOutlet, "SELECT Id,name FROM item_categories WHERE (del_status IS NULL OR del_status='Live') ORDER BY name", "All Groups");
            }
            else if (f.Contains("outlet"))
                LoadCombo(cmbOutlet, "SELECT Id,outlet_name AS name FROM outlets WHERE (del_status IS NULL OR del_status='Live') ORDER BY Id", "All Outlets");

            if (f.Contains("customer"))
                LoadCombo(cmbCustomer, "SELECT Id,name FROM customers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name", "All Customers");
            if (f.Contains("supplier"))
                LoadCombo(cmbSupplier, "SELECT Id,name FROM suppliers WHERE (del_status IS NULL OR del_status='Live') ORDER BY name", "All Suppliers");
            if (f.Contains("employee"))
                LoadCombo(cmbEmployee, "SELECT Id,name FROM employees WHERE (del_status IS NULL OR del_status='Live') ORDER BY name", "All Employees");
            if (f.Contains("item"))
                LoadCombo(cmbItem, "SELECT Id,name FROM items WHERE (del_status IS NULL OR del_status='Live') ORDER BY name", "All Items");
        }

        private void LoadCombo(ComboBox cmb, string sql, string allLabel)
        {
            var list = new List<ReportComboItem> { new(0, allLabel) };
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r.IsDBNull(0) ? 0 : r.GetInt64(0);
                    string name = r.IsDBNull(1) ? "" : r.GetString(1);
                    list.Add(new ReportComboItem(id, name));
                }
            }
            catch { }
            cmb.ItemsSource = list;
            cmb.SelectedIndex = 0;
        }

        // ── Load / refresh data ─────────────────────────────────────────────
        private void LoadData()
        {
            loadingBar.Visibility = Visibility.Visible;
            emptyState.Visibility = Visibility.Collapsed;
            dataGrid.Visibility   = Visibility.Collapsed;
            totalsBar.Visibility  = Visibility.Collapsed;
            rowCountBar.Visibility = Visibility.Collapsed;

            try
            {
                string sql = ApplyFilters(_spec.Sql);
                using var conn = _db.GetConnection();
                using var cmd  = conn.CreateCommand();
                cmd.CommandText = sql;
                if (sql.Contains("@from"))
                {
                    cmd.Parameters.AddWithValue("@from", dpFrom.SelectedDate?.ToString("yyyy-MM-dd") ?? "1900-01-01");
                    cmd.Parameters.AddWithValue("@to",   dpTo.SelectedDate?.ToString("yyyy-MM-dd") ?? "2099-12-31");
                }
                var table = new DataTable();
                using (var r = cmd.ExecuteReader()) table.Load(r);
                _currentTable = table;
                ShowData(table);
            }
            catch (Exception ex)
            {
                loadingBar.Visibility = Visibility.Collapsed;
                emptyState.Visibility = Visibility.Visible;
                MessageBox.Show("Error: " + ex.Message, "Report Error", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void ShowData(DataTable table)
        {
            loadingBar.Visibility = Visibility.Collapsed;
            if (table.Rows.Count == 0)
            {
                emptyState.Visibility = Visibility.Visible;
                dataGrid.Visibility   = Visibility.Collapsed;
                return;
            }

            emptyState.Visibility  = Visibility.Collapsed;
            dataGrid.Visibility    = Visibility.Visible;
            dataGrid.ItemsSource   = table.DefaultView;

            BuildTotals(table);
            rowCountBar.Visibility = Visibility.Visible;
            lblRowCount.Text = $"Showing {table.Rows.Count} entries";
        }

        // ── Build column totals footer ──────────────────────────────────────
        private void BuildTotals(DataTable table)
        {
            totalsPanel.Children.Clear();
            bool any = false;
            foreach (DataColumn col in table.Columns)
            {
                string lower = col.ColumnName.ToLowerInvariant();
                bool isMoney = lower.Contains("total") || lower.Contains("amount") || lower.Contains("price")
                    || lower.Contains("paid") || lower.Contains("due") || lower.Contains("profit")
                    || lower.Contains("tax") || lower.Contains("vat") || lower.Contains("charge")
                    || lower.Contains("discount") || lower.Contains("salary") || lower.Contains("loss")
                    || lower.Contains("income") || lower.Contains("expense") || lower.Contains("payable")
                    || lower.Contains("credit") || lower.Contains("debit") || lower.Contains("balance");
                bool isQty = lower == "qty" || lower == "quantity" || lower.Contains("stock_qty")
                    || lower.Contains("items") || lower.Contains("count");
                if (!isMoney && !isQty) continue;

                double sum = 0;
                bool ok = true;
                foreach (DataRow row in table.Rows)
                {
                    if (!double.TryParse(row[col]?.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double d))
                    { ok = false; break; }
                    sum += d;
                }
                if (!ok) continue;

                string label = string.Join(" ", col.ColumnName.Split('_'));
                string val = isMoney ? $"₹ {Math.Round(sum, 2):N2}" : $"{sum:N0}";

                var chip = new Border
                {
                    Background = new SolidColorBrush(Color.FromRgb(0xEE, 0xF2, 0xFF)),
                    BorderBrush = new SolidColorBrush(Color.FromRgb(0xC7, 0xD2, 0xFE)),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(6),
                    Padding = new Thickness(12, 7, 12, 7),
                    Margin = new Thickness(0, 0, 8, 0)
                };
                var sp = new StackPanel { Orientation = Orientation.Horizontal };
                sp.Children.Add(new TextBlock
                {
                    Text = label.ToUpper() + ": ",
                    FontSize = 11.5, FontWeight = FontWeights.SemiBold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x55, 0x65, 0x81)),
                    FontFamily = new FontFamily("Inter, Segoe UI"), VerticalAlignment = VerticalAlignment.Center
                });
                sp.Children.Add(new TextBlock
                {
                    Text = val,
                    FontSize = 12.5, FontWeight = FontWeights.Bold,
                    Foreground = new SolidColorBrush(Color.FromRgb(0x43, 0x38, 0xCA)),
                    FontFamily = new FontFamily("Inter, Segoe UI"), VerticalAlignment = VerticalAlignment.Center
                });
                chip.Child = sp;
                totalsPanel.Children.Add(chip);
                any = true;
            }
            totalsBar.Visibility = any ? Visibility.Visible : Visibility.Collapsed;
        }

        // ── Apply SQL filter markers ─────────────────────────────────────────
        private string ApplyFilters(string sql)
        {
            long outletId   = GetId(cmbOutlet);
            long customerId = GetId(cmbCustomer);
            long supplierId = GetId(cmbSupplier);
            long employeeId = GetId(cmbEmployee);
            long itemId     = GetId(cmbItem);

            var replaced = new HashSet<string>();
            foreach (System.Text.RegularExpressions.Match m in
                System.Text.RegularExpressions.Regex.Matches(sql, @"\{FILTER:(\w+) on (\w+)\}"))
            {
                if (replaced.Contains(m.Value)) continue;
                string col   = m.Groups[1].Value;
                string alias = m.Groups[2].Value;
                long id = col switch
                {
                    "outlet_id"   => outletId,
                    "customer_id" => customerId,
                    "supplier_id" => supplierId,
                    "employee_id" => employeeId,
                    "category_id" => outletId,   // reuse outlet combo for category
                    "item_id"     => itemId,
                    _             => 0
                };
                string repl = id > 0 ? $" AND {alias}.{col} = {id}" : "";
                sql = sql.Replace(m.Value, repl);
                replaced.Add(m.Value);
            }
            return sql;
        }

        private long GetId(ComboBox cmb)
            => cmb.SelectedItem is ReportComboItem ci ? ci.Id : 0;

        // ── Filter info bar ─────────────────────────────────────────────────
        private void ShowFilterInfo()
        {
            filterInfoPanel.Children.Clear();
            bool any = false;

            AddInfo("Report", _spec.Title);
            if (_spec.DateFilter && dpFrom.SelectedDate.HasValue && dpTo.SelectedDate.HasValue)
            { AddInfo("Date Range", $"{dpFrom.SelectedDate:dd MMM yyyy}  →  {dpTo.SelectedDate:dd MMM yyyy}"); any = true; }

            if (pnlOutlet.Visibility == Visibility.Visible && GetId(cmbOutlet) > 0)
            { AddInfo(lblOutletLabel.Text, ((ReportComboItem)cmbOutlet.SelectedItem).Name); any = true; }
            if (pnlCustomer.Visibility == Visibility.Visible && GetId(cmbCustomer) > 0)
            { AddInfo("Customer", ((ReportComboItem)cmbCustomer.SelectedItem).Name); any = true; }
            if (pnlSupplier.Visibility == Visibility.Visible && GetId(cmbSupplier) > 0)
            { AddInfo("Supplier", ((ReportComboItem)cmbSupplier.SelectedItem).Name); any = true; }
            if (pnlEmployee.Visibility == Visibility.Visible && GetId(cmbEmployee) > 0)
            { AddInfo("Employee", ((ReportComboItem)cmbEmployee.SelectedItem).Name); any = true; }

            AddInfo("Generated", DateTime.Now.ToString("dd MMM yyyy, hh:mm tt"));
            filterInfoBar.Visibility = any ? Visibility.Visible : Visibility.Collapsed;
        }

        private void AddInfo(string label, string value)
        {
            var sp = new StackPanel { Orientation = Orientation.Horizontal, Margin = new Thickness(0, 0, 0, 2) };
            sp.Children.Add(new TextBlock { Text = label + ": ", FontSize = 12, FontWeight = FontWeights.SemiBold,
                Foreground = new SolidColorBrush(Color.FromRgb(0x0F, 0x17, 0x2A)), FontFamily = new FontFamily("Inter, Segoe UI") });
            sp.Children.Add(new TextBlock { Text = value, FontSize = 12,
                Foreground = new SolidColorBrush(Color.FromRgb(0x33, 0x41, 0x55)), FontFamily = new FontFamily("Inter, Segoe UI") });
            filterInfoPanel.Children.Add(sp);
        }

        // ── Event handlers ───────────────────────────────────────────────────
        private void BtnBack_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ReportsIndexPage(_dashboard));

        private void BtnBackBreadcrumb_Click(object sender, RoutedEventArgs e)
            => _dashboard?.ShowPage(new ReportsIndexPage(_dashboard));

        private void BtnToggleFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = filterSection.Visibility == Visibility.Collapsed
               ? Visibility.Visible : Visibility.Collapsed;

        private void BtnApplyFilter_Click(object sender, RoutedEventArgs e)
        {
            filterSection.Visibility = Visibility.Collapsed;
            LoadData();
            ShowFilterInfo();
        }

        private void BtnCancelFilter_Click(object sender, RoutedEventArgs e)
            => filterSection.Visibility = Visibility.Collapsed;

        private void DatePicker_Changed(object sender, SelectionChangedEventArgs e) { }
        private void Filter_Changed(object sender, SelectionChangedEventArgs e) { }

        private void BtnExport_Click(object sender, RoutedEventArgs e)
            => exportPopup.IsOpen = !exportPopup.IsOpen;

        // ── Export CSV ───────────────────────────────────────────────────────
        private void BtnExportCsv_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0)
            { MessageBox.Show("No data to export.", "Info"); return; }
            var dlg = new SaveFileDialog
            {
                Filter = "CSV files (*.csv)|*.csv",
                FileName = _spec.Title.Replace(" ", "_") + "_" + DateTime.Today.ToString("yyyyMMdd") + ".csv"
            };
            if (dlg.ShowDialog() != true) return;
            try
            {
                using var sw = new StreamWriter(dlg.FileName, false, System.Text.Encoding.UTF8);
                // Header
                var cols = _currentTable.Columns.Cast<DataColumn>().Select(c => c.ColumnName).ToList();
                sw.WriteLine(string.Join(",", cols));
                // Rows
                foreach (DataRow row in _currentTable.Rows)
                {
                    var vals = cols.Select(c => "\"" + (row[c]?.ToString() ?? "").Replace("\"", "\"\"") + "\"");
                    sw.WriteLine(string.Join(",", vals));
                }
                MessageBox.Show("Exported: " + dlg.FileName, "Success", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex) { MessageBox.Show("Export failed: " + ex.Message, "Error"); }
        }

        // ── Print ────────────────────────────────────────────────────────────
        private void BtnPrint_Click(object sender, RoutedEventArgs e)
        {
            exportPopup.IsOpen = false;
            if (_currentTable == null || _currentTable.Rows.Count == 0)
            { MessageBox.Show("No data to print.", "Info"); return; }

            var dlg = new PrintDialog();
            if (dlg.ShowDialog() != true) return;

            var doc = new FlowDocument { FontFamily = new FontFamily("Segoe UI"), FontSize = 11, PagePadding = new Thickness(40) };
            var title = new Paragraph(new Run(_spec.Title)) { FontSize = 16, FontWeight = FontWeights.Bold, Margin = new Thickness(0,0,0,10) };
            doc.Blocks.Add(title);

            if (_spec.DateFilter)
            {
                var dates = new Paragraph(new Run($"Period: {dpFrom.SelectedDate:dd MMM yyyy} to {dpTo.SelectedDate:dd MMM yyyy}"))
                { FontSize = 11, Foreground = Brushes.Gray, Margin = new Thickness(0,0,0,12) };
                doc.Blocks.Add(dates);
            }

            var table = new System.Windows.Documents.Table { CellSpacing = 2 };
            int colCount = _currentTable.Columns.Count;
            for (int i = 0; i < colCount; i++)
                table.Columns.Add(new TableColumn { Width = new GridLength(1, GridUnitType.Star) });

            var rg = new TableRowGroup();
            // Header row
            var hdr = new TableRow { Background = new SolidColorBrush(Color.FromRgb(0x69, 0x6c, 0xff)) };
            foreach (DataColumn col in _currentTable.Columns)
            {
                hdr.Cells.Add(new TableCell(new Paragraph(new Run(col.ColumnName.Replace("_", " ").ToUpper())))
                { Padding = new Thickness(4,3,4,3), Foreground = Brushes.White, FontWeight = FontWeights.SemiBold });
            }
            rg.Rows.Add(hdr);

            // Data rows
            bool alt = false;
            foreach (DataRow row in _currentTable.Rows)
            {
                var tr = new TableRow { Background = alt ? new SolidColorBrush(Color.FromRgb(0xFA, 0xFA, 0xFF)) : Brushes.White };
                foreach (DataColumn col in _currentTable.Columns)
                    tr.Cells.Add(new TableCell(new Paragraph(new Run(row[col]?.ToString() ?? ""))) { Padding = new Thickness(4, 2, 4, 2) });
                rg.Rows.Add(tr);
                alt = !alt;
            }
            table.RowGroups.Add(rg);
            doc.Blocks.Add(table);

            var docPaginator = ((IDocumentPaginatorSource)doc).DocumentPaginator;
            docPaginator.PageSize = new Size(dlg.PrintableAreaWidth, dlg.PrintableAreaHeight);
            dlg.PrintDocument(docPaginator, _spec.Title);
        }
    }
}
