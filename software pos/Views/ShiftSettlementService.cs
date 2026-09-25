using System;
using System.Collections.Generic;
using System.Linq;
using RashanKiDukan.Database;
using RashanKiDukan.Models;

namespace RashanKiDukan.Views
{
    /// <summary>
    /// Day Close / Shift Settlement (Z-Report) ka calculation service.
    /// Shift ke start time se ab tak ke saare figures SQLite se nikalta hai
    /// (RegisterDialog.BuildSummary jaise hi date filters), phir prompt ke
    /// logic se shortage/excess calculate karta hai.
    /// </summary>
    public class ShiftSettlementService
    {
        public class PaymentMethodRow
        {
            public long Id { get; set; }
            public string Name { get; set; } = "";
        }

        public class SettlementData
        {
            // ── Header info ──
            public string CounterName { get; set; } = "Counter 1";
            public string CashierName { get; set; } = "Cashier";

            // ── Bill / Sales ──
            public int TotalBills { get; set; }
            public double TotalCashSales { get; set; }
            public double TotalCardSales { get; set; }
            public double TotalUpiSales { get; set; }
            public double CardEnteredAmount { get; set; }      // C.CRD AMT ENT (machine par daala gaya)
            public double FreeSchemeValue { get; set; }        // FREE SCHEME / SUGAR

            // ── Returns ──
            public double SaleReturnAdj { get; set; }          // non-cash return (same method adjustment)
            public double SaleReturnCash { get; set; }         // cash diya gaya return

            // ── Counter / reconciliation inputs ──
            public double OpeningCashFloat { get; set; }
            public double CashierSubmittedCash { get; set; }
            public double CashAdvances { get; set; }
            public double VoucherAmount { get; set; }

            // ── Loyalty ──
            public int LoyaltyCardsIssued { get; set; }
            public int LoyaltyCardsRenewed { get; set; }

            // ── Pending (tobe) ──
            public double SaleReturnTobe { get; set; }

            // ── Derived (computed) ──
            public double GrossBillAmount => TotalCashSales + TotalCardSales + TotalUpiSales;
            public double CashAfterReturn => TotalCashSales - SaleReturnCash;
            public double NetTotalAmount => GrossBillAmount - SaleReturnAdj - SaleReturnCash;

            public double ExpectedCash => OpeningCashFloat + CashAfterReturn - CashAdvances;
            public double Difference => CashierSubmittedCash - ExpectedCash;

            public double ShortageAmount => Difference < 0 ? Math.Abs(Difference) : 0;
            public double ExcessAmount => Difference > 0 ? Difference : 0;
        }

        private readonly DatabaseService _db;
        private readonly long _userId;
        private readonly long _outletId = 1;
        private readonly long _companyId;

        public ShiftSettlementService(DatabaseService db, User? user)
        {
            _db = db;
            _userId = user?.Id ?? 1;
            _companyId = user?.CompanyId ?? 1;
            _cashierName = string.IsNullOrWhiteSpace(user?.FullName) ? "Cashier" : user!.FullName;
        }

        private readonly string _cashierName;

        /// <summary>Current open register ka opening time — shift window ka start.</summary>
        private string GetShiftStartTime()
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT IFNULL(opening_balance_date_time, created_at) FROM registers
                    WHERE user_id=@u AND outlet_id=@o AND company_id=@c
                      AND del_status='Live' AND register_status=1
                    ORDER BY id DESC LIMIT 1";
                cmd.Parameters.AddWithValue("@u", _userId);
                cmd.Parameters.AddWithValue("@o", _outletId);
                cmd.Parameters.AddWithValue("@c", _companyId);
                var v = cmd.ExecuteScalar()?.ToString();
                return string.IsNullOrEmpty(v) ? DateTime.Now.ToString("yyyy-MM-dd 00:00:00") : v;
            }
            catch { return DateTime.Now.ToString("yyyy-MM-dd 00:00:00"); }
        }

        /// <summary>
        /// Saare figures DB se collect karo. `submittedCash` cashier ne modal me
        /// physically count karke diya; advances/vouchers bhi modal inputs hain.
        /// </summary>
        public SettlementData Collect(double submittedCash, double advances, double vouchers)
        {
            var d = new SettlementData
            {
                CashierSubmittedCash = submittedCash,
                CashAdvances = advances,
                VoucherAmount = vouchers,
                CashierName = _cashierName,
            };

            string openDt = GetShiftStartTime();
            string openDate = openDt.Length >= 10 ? openDt.Substring(0, 10) : openDt;
            string nowDt = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");
            string nowDate = DateTime.Now.ToString("yyyy-MM-dd");

            // Payment method ids by name (Cash / Card / UPI variants)
            long cashId = 0, cardId = 0, upiId = 0;
            var pmMap = new Dictionary<string, long>();
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT id, name FROM payment_methods WHERE del_status='Live'";
                using var r = cmd.ExecuteReader();
                while (r.Read())
                {
                    long id = r.GetInt64(0);
                    string name = (r.IsDBNull(1) ? "" : r.GetString(1)).Trim().ToLowerInvariant();
                    pmMap[name] = id;
                }
            }
            catch { }
            cashId = pmMap.TryGetValue("cash", out var c) ? c : 1;
            cardId = pmMap.FirstOrDefault(k => k.Key.Contains("card")).Value;
            upiId = pmMap.FirstOrDefault(k => k.Key.Contains("upi") || k.Key.Contains("qr")).Value;

            // ── Per-method sale totals (RegisterDialog.BuildSummary jaisa hi window) ──
            double SaleByMethod(long pmId) => Sum(@"
                SELECT IFNULL(SUM(sp.amount),0) FROM sale_payments sp JOIN sales s ON s.id=sp.sale_id
                WHERE sp.del_status='Live' AND sp.payment_id=@pm AND s.del_status='Live'
                  AND s.user_id=@u AND s.outlet_id=@o AND s.company_id=@c
                  AND ((s.date_time IS NOT NULL AND s.date_time BETWEEN @open AND @now)
                    OR (s.date_time IS NULL AND s.sale_date BETWEEN @od AND @nd)
                    OR (s.date_time IS NULL AND s.sale_date IS NULL AND s.created_at BETWEEN @open AND @now))",
                pmId, openDt, nowDt, openDate, nowDate);

            d.TotalCashSales = SaleByMethod(cashId);
            d.TotalCardSales = cardId > 0 ? SaleByMethod(cardId) : 0;
            d.TotalUpiSales = upiId > 0 ? SaleByMethod(upiId) : 0;
            // C.CRD AMT ENT — card machine par enter kiya gaya amount (abhi = card sales)
            d.CardEnteredAmount = d.TotalCardSales;

            // Total bills
            d.TotalBills = (int)Sum(@"
                SELECT COUNT(*) FROM sales s
                WHERE s.del_status='Live' AND s.user_id=@u AND s.outlet_id=@o AND s.company_id=@c
                  AND ((s.date_time IS NOT NULL AND s.date_time BETWEEN @open AND @now)
                    OR (s.date_time IS NULL AND s.sale_date BETWEEN @od AND @nd)
                    OR (s.date_time IS NULL AND s.sale_date IS NULL AND s.created_at BETWEEN @open AND @now))",
                0, openDt, nowDt, openDate, nowDate);

            // Sale returns — cash vs adjustment (same-method credit)
            d.SaleReturnCash = Sum(@"
                SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns
                WHERE del_status='Live' AND payment_method_id=@pm
                  AND user_id=@u AND outlet_id=@o AND company_id=@c
                  AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))",
                cashId, openDt, nowDt, openDate, nowDate);
            double retCard = cardId > 0 ? Sum(@"
                SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns
                WHERE del_status='Live' AND payment_method_id=@pm
                  AND user_id=@u AND outlet_id=@o AND company_id=@c
                  AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))",
                cardId, openDt, nowDt, openDate, nowDate) : 0;
            double retUpi = upiId > 0 ? Sum(@"
                SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns
                WHERE del_status='Live' AND payment_method_id=@pm
                  AND user_id=@u AND outlet_id=@o AND company_id=@c
                  AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))",
                upiId, openDt, nowDt, openDate, nowDate) : 0;
            d.SaleReturnAdj = retCard + retUpi;

            // Pending returns (tobe) — jo returns abhi pending status me hain
            d.SaleReturnTobe = Sum(@"
                SELECT IFNULL(SUM(total_return_amount),0) FROM sale_returns
                WHERE del_status='Live' AND (LOWER(IFNULL(status,'')) LIKE '%pend%' OR status IS NULL OR status='')
                  AND user_id=@u AND outlet_id=@o AND company_id=@c
                  AND ((date BETWEEN @od AND @nd) OR (date IS NULL AND created_at BETWEEN @open AND @now))",
                0, openDt, nowDt, openDate, nowDate);

            // Opening cash float — register ke opening_details JSON me cash method ka amount
            d.OpeningCashFloat = GetOpeningCash(cashId);

            // Free scheme / sugar value — free items ka value (scheme-based discount as item value)
            d.FreeSchemeValue = Sum(@"
                SELECT IFNULL(SUM(sd.qty * IFNULL(NULLIF(i.purchase_price,0), sd.menu_unit_price)),0)
                FROM sale_details sd JOIN sales s ON s.id=sd.sales_id
                LEFT JOIN items i ON i.id=sd.item_id
                WHERE s.del_status='Live' AND s.user_id=@u AND s.outlet_id=@o AND s.company_id=@c
                  AND IFNULL(sd.menu_price_with_discount,0) = 0 AND IFNULL(sd.qty,0) > 0
                  AND ((s.date_time IS NOT NULL AND s.date_time BETWEEN @open AND @now)
                    OR (s.date_time IS NULL AND s.sale_date BETWEEN @od AND @nd)
                    OR (s.date_time IS NULL AND s.sale_date IS NULL AND s.created_at BETWEEN @open AND @now))",
                0, openDt, nowDt, openDate, nowDate);

            // Loyalty cards issued / renewed aaj
            d.LoyaltyCardsIssued = (int)Sum(@"
                SELECT COUNT(*) FROM customers
                WHERE (del_status IS NULL OR del_status!='Deleted')
                  AND (loyalty_point > 0) AND (date(created_at) = @nd) AND user_id=@u",
                0, openDt, nowDt, openDate, nowDate);
            d.LoyaltyCardsRenewed = (int)Sum(@"
                SELECT COUNT(*) FROM business_club_transactions
                WHERE (LOWER(IFNULL(type,'')) LIKE '%renew%') AND (date(created_at) = @nd)",
                0, openDt, nowDt, openDate, nowDate);

            return d;
        }

        private double GetOpeningCash(long cashId)
        {
            try
            {
                // registers.opening_details format: ["<pmId>||<name>||<amount>", ...]
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = @"SELECT IFNULL(opening_details,'') FROM registers
                    WHERE user_id=@u AND outlet_id=@o AND company_id=@c
                      AND del_status='Live' AND register_status=1
                    ORDER BY id DESC LIMIT 1";
                cmd.Parameters.AddWithValue("@u", _userId);
                cmd.Parameters.AddWithValue("@o", _outletId);
                cmd.Parameters.AddWithValue("@c", _companyId);
                var json = cmd.ExecuteScalar()?.ToString() ?? "";
                if (string.IsNullOrWhiteSpace(json)) return 0;

                double total = 0;
                using var doc = System.Text.Json.JsonDocument.Parse(json);
                if (doc.RootElement.ValueKind == System.Text.Json.JsonValueKind.Array)
                {
                    foreach (var el in doc.RootElement.EnumerateArray())
                    {
                        var parts = el.GetString()?.Split("||");
                        if (parts == null || parts.Length < 3) continue;
                        if (!long.TryParse(parts[0], out var pid)) continue;
                        // Cash method ka amount hi opening float hai
                        if (pid == cashId && double.TryParse(parts[2], System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var amt))
                            total += amt;
                    }
                }
                return total;
            }
            catch { return 0; }
        }

        /// <summary>
        /// F10 par register open karte waqt dale gaye per-method opening amounts
        /// (Cash / UPI / Card) — F11 dialog me wapas dikhane ke liye.
        /// </summary>
        public (double Cash, double Upi, double Card) GetOpeningByMethod()
        {
            double cash = 0, upi = 0, card = 0;
            try
            {
                // Payment method ids by name (Collect jaisa hi matching)
                var pmMap = new Dictionary<string, long>();
                using (var conn = _db.GetConnection())
                using (var cmd = conn.CreateCommand())
                {
                    cmd.CommandText = "SELECT id, name FROM payment_methods WHERE del_status='Live'";
                    using var r = cmd.ExecuteReader();
                    while (r.Read())
                        pmMap[(r.IsDBNull(1) ? "" : r.GetString(1)).Trim().ToLowerInvariant()] = r.GetInt64(0);
                }
                long cashId = pmMap.TryGetValue("cash", out var c) ? c : 1;
                long cardId = pmMap.FirstOrDefault(k => k.Key.Contains("card")).Value;
                long upiId = pmMap.FirstOrDefault(k => k.Key.Contains("upi") || k.Key.Contains("qr")).Value;

                using var conn2 = _db.GetConnection();
                using var cmd2 = conn2.CreateCommand();
                cmd2.CommandText = @"SELECT IFNULL(opening_details,'') FROM registers
                    WHERE user_id=@u AND outlet_id=@o AND company_id=@c
                      AND del_status='Live' AND register_status=1
                    ORDER BY id DESC LIMIT 1";
                cmd2.Parameters.AddWithValue("@u", _userId);
                cmd2.Parameters.AddWithValue("@o", _outletId);
                cmd2.Parameters.AddWithValue("@c", _companyId);
                var json = cmd2.ExecuteScalar()?.ToString() ?? "";
                if (string.IsNullOrWhiteSpace(json)) return (0, 0, 0);

                using var doc = System.Text.Json.JsonDocument.Parse(json);
                if (doc.RootElement.ValueKind == System.Text.Json.JsonValueKind.Array)
                {
                    foreach (var el in doc.RootElement.EnumerateArray())
                    {
                        var parts = el.GetString()?.Split("||");
                        if (parts == null || parts.Length < 3) continue;
                        if (!long.TryParse(parts[0], out var pid)) continue;
                        if (!double.TryParse(parts[2], System.Globalization.NumberStyles.Any, System.Globalization.CultureInfo.InvariantCulture, out var amt)) continue;

                        if (pid == cashId) cash += amt;
                        else if (pid == upiId) upi += amt;
                        else if (pid == cardId) card += amt;
                    }
                }
            }
            catch { }
            return (cash, upi, card);
        }

        /// <summary>Scalar helper — parameters: @pm(optional), @u, @o, @c, @open, @now, @od, @nd</summary>
        private double Sum(string sql, long pmId, string openDt, string nowDt, string openDate, string nowDate)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = sql;
                if (sql.Contains("@pm"))
                {
                    var p = cmd.CreateParameter();
                    p.ParameterName = "@pm";
                    p.Value = pmId;
                    cmd.Parameters.Add(p);
                }
                foreach (var (name, value) in new (string, object)[]
                {
                    ("@u", _userId), ("@o", _outletId), ("@c", _companyId),
                    ("@open", openDt), ("@now", nowDt), ("@od", openDate), ("@nd", nowDate)
                })
                {
                    if (sql.Contains(name))
                    {
                        var p = cmd.CreateParameter();
                        p.ParameterName = name;
                        p.Value = value;
                        cmd.Parameters.Add(p);
                    }
                }
                var v = cmd.ExecuteScalar();
                return v == null || v == DBNull.Value ? 0 : Convert.ToDouble(v);
            }
            catch { return 0; }
        }
    }
}
