using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.RegularExpressions;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Enterprise GST & HSN Validation Service.
    /// Implements Indian government standard GSTIN checksum algorithm
    /// and HSN code validation with auto-suggestion.
    /// </summary>
    public static class GstValidationService
    {
        // ═══════════════════════════════════════════════════════════
        // GSTIN VALIDATION (15-character Government Standard)
        // Format: 22AAAAA0000A1Z5
        //   [0-1]  = State Code (01-37)
        //   [2-11] = PAN (10 chars)
        //   [12]   = Entity Number (1-9, A-Z)
        //   [13]   = 'Z' (default)
        //   [14]   = Checksum (Luhn mod 36)
        // ═══════════════════════════════════════════════════════════

        private static readonly Regex GstinPattern = new(
            @"^[0-3][0-9][A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$",
            RegexOptions.Compiled);

        /// <summary>
        /// Validate GSTIN with full checksum verification.
        /// Returns (isValid, errorMessage, stateCode, stateName).
        /// </summary>
        public static GstinValidationResult ValidateGstin(string gstin)
        {
            if (string.IsNullOrWhiteSpace(gstin))
                return new GstinValidationResult(false, "GSTIN is empty");

            gstin = gstin.Trim().ToUpper();

            if (gstin.Length != 15)
                return new GstinValidationResult(false, $"GSTIN must be 15 characters (got {gstin.Length})");

            if (!GstinPattern.IsMatch(gstin))
                return new GstinValidationResult(false, "Invalid GSTIN format. Expected: 22AAAAA0000A1Z5");

            // State code validation (01-37)
            string stateCodeStr = gstin.Substring(0, 2);
            int stateCode = int.Parse(stateCodeStr);
            if (stateCode < 1 || stateCode > 37)
                return new GstinValidationResult(false, $"Invalid state code: {stateCodeStr}");

            string stateName = GetStateName(stateCode);
            if (stateName == "Unknown")
                return new GstinValidationResult(false, $"Unknown state code: {stateCodeStr}");

            // PAN validation (chars 2-11)
            string pan = gstin.Substring(2, 10);
            if (!Regex.IsMatch(pan, @"^[A-Z]{5}[0-9]{4}[A-Z]$"))
                return new GstinValidationResult(false, "Invalid PAN embedded in GSTIN");

            // Entity type from PAN (4th character)
            char entityType = pan[3];
            string entityDesc = GetPanEntityType(entityType);

            // Checksum validation (Luhn Mod 36)
            if (!VerifyGstinChecksum(gstin))
                return new GstinValidationResult(false, "GSTIN checksum verification failed — invalid number");

            return new GstinValidationResult(true, "Valid GSTIN", stateCode, stateName, pan, entityDesc);
        }

        /// <summary>
        /// Verify GSTIN checksum using the government's Luhn Mod 36 algorithm.
        /// </summary>
        private static bool VerifyGstinChecksum(string gstin)
        {
            const string chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            int sum = 0;

            for (int i = 0; i < 14; i++)
            {
                int idx = chars.IndexOf(gstin[i]);
                if (idx < 0) return false;

                // Factor: position (1-based) alternates between 1 and 2
                int factor = (i % 2 == 0) ? 1 : 2;
                int product = idx * factor;

                // Quotient + Remainder in base 36
                sum += (product / 36) + (product % 36);
            }

            int remainder = sum % 36;
            int checkDigit = (36 - remainder) % 36;
            char expected = chars[checkDigit];

            return gstin[14] == expected;
        }

        /// <summary>
        /// Quick check — is this a valid GSTIN format (without detailed error message)?
        /// </summary>
        public static bool IsValidGstin(string gstin)
            => ValidateGstin(gstin).IsValid;

        /// <summary>
        /// Online GSTIN verification — fetches registered business name.
        /// Uses our own Laravel cloud as proxy (which scrapes GST portal).
        /// Falls back gracefully if no internet.
        /// </summary>
        public static async Task<GstinOnlineResult> VerifyGstinOnlineAsync(string gstin)
        {
            gstin = gstin?.Trim().ToUpper() ?? "";
            var offline = ValidateGstin(gstin);
            if (!offline.IsValid)
                return new GstinOnlineResult(false, offline.Message);

            try
            {
                using var client = new System.Net.Http.HttpClient { Timeout = TimeSpan.FromSeconds(12) };
                client.DefaultRequestHeaders.Add("User-Agent", "RashanKiDukan-POS/1.0");

                // Use our own Laravel cloud endpoint (it caches results + handles GST portal scraping)
                string serverUrl = new Database.DatabaseService().GetAppSetting("server_url") ?? "";
                if (!string.IsNullOrEmpty(serverUrl))
                {
                    string url = $"{serverUrl.TrimEnd('/')}/api/gst-lookup/{gstin}";
                    // Get token (try encrypted first, then plain)
                    var db = new Database.DatabaseService();
                    string? token = SecureSettingsService.DecryptAndGet(db, "server_token")
                                    ?? db.GetAppSetting("server_token");
                    if (!string.IsNullOrEmpty(token))
                        client.DefaultRequestHeaders.Add("Authorization", $"Bearer {token}");

                    var response = await client.GetAsync(url);

                    // If unauthorized, try to re-login and retry
                    if (response.StatusCode == System.Net.HttpStatusCode.Unauthorized)
                    {
                        var apiService = new ApiService();
                        if (await apiService.EnsureTokenAsync())
                        {
                            token = SecureSettingsService.DecryptAndGet(db, "server_token")
                                    ?? db.GetAppSetting("server_token");
                            client.DefaultRequestHeaders.Remove("Authorization");
                            if (!string.IsNullOrEmpty(token))
                                client.DefaultRequestHeaders.Add("Authorization", $"Bearer {token}");
                            response = await client.GetAsync(url);
                        }
                    }
                    if (response.IsSuccessStatusCode)
                    {
                        string body = await response.Content.ReadAsStringAsync();
                        using var doc = System.Text.Json.JsonDocument.Parse(body);
                        var root = doc.RootElement;

                        if (root.TryGetProperty("data", out var data))
                        {
                            string legalName = data.TryGetProperty("legal_name", out var ln) ? ln.GetString() ?? "" : "";
                            string tradeName = data.TryGetProperty("trade_name", out var tn) ? tn.GetString() ?? "" : "";
                            string status = data.TryGetProperty("status", out var st) ? st.GetString() ?? "" : "";
                            string addr = data.TryGetProperty("address", out var ad) ? ad.GetString() ?? "" : "";

                            if (!string.IsNullOrEmpty(legalName))
                            {
                                return new GstinOnlineResult(true, "Verified", legalName, tradeName, status, addr,
                                    offline.StateCode, offline.StateName);
                            }
                        }
                    }
                }

                // Fallback: return offline-only result
                return new GstinOnlineResult(true, "Valid (online lookup unavailable)",
                    "", "", "", "", offline.StateCode, offline.StateName);
            }
            catch
            {
                return new GstinOnlineResult(true, "Valid (offline — no internet)",
                    "", "", "", "", offline.StateCode, offline.StateName);
            }
        }

        /// <summary>
        /// Extract state code (first 2 digits) from GSTIN.
        /// Returns 0 if invalid.
        /// </summary>
        public static int ExtractStateCode(string gstin)
        {
            if (string.IsNullOrWhiteSpace(gstin) || gstin.Length < 2) return 0;
            if (int.TryParse(gstin.Substring(0, 2), out int code) && code >= 1 && code <= 37)
                return code;
            return 0;
        }

        /// <summary>
        /// Determine if transaction is inter-state (IGST) or intra-state (CGST+SGST).
        /// </summary>
        public static GstType DetermineGstType(string sellerGstin, string buyerGstin)
        {
            int sellerState = ExtractStateCode(sellerGstin);
            int buyerState = ExtractStateCode(buyerGstin);

            if (sellerState == 0 || buyerState == 0)
                return GstType.IntraState; // Default to CGST+SGST if can't determine

            return sellerState == buyerState ? GstType.IntraState : GstType.InterState;
        }

        /// <summary>
        /// Check if E-Way Bill is required (Interstate > ₹50,000).
        /// </summary>
        public static bool IsEwayBillRequired(double invoiceAmount, string sellerGstin, string buyerGstin)
        {
            if (invoiceAmount < 50000) return false;
            return DetermineGstType(sellerGstin, buyerGstin) == GstType.InterState;
        }

        // ═══════════════════════════════════════════════════════════
        // HSN CODE VALIDATION
        // Valid lengths: 4, 6, or 8 digits
        // Turnover > 5Cr: 6 digits required
        // Turnover > 10Cr: 8 digits required (SAC for services)
        // ═══════════════════════════════════════════════════════════

        private static readonly Regex HsnPattern = new(@"^\d{4}(\d{2})?(\d{2})?$", RegexOptions.Compiled);
        private static readonly Regex SacPattern = new(@"^99\d{2}(\d{2})?$", RegexOptions.Compiled);

        /// <summary>
        /// Validate HSN/SAC code format.
        /// </summary>
        public static HsnValidationResult ValidateHsn(string hsnCode)
        {
            if (string.IsNullOrWhiteSpace(hsnCode))
                return new HsnValidationResult(false, "HSN code is empty");

            hsnCode = hsnCode.Trim();

            if (!Regex.IsMatch(hsnCode, @"^\d+$"))
                return new HsnValidationResult(false, "HSN code must contain only digits");

            if (hsnCode.Length != 4 && hsnCode.Length != 6 && hsnCode.Length != 8)
                return new HsnValidationResult(false, $"HSN code must be 4, 6, or 8 digits (got {hsnCode.Length})");

            bool isSac = hsnCode.StartsWith("99");
            string type = isSac ? "SAC (Service)" : "HSN (Goods)";

            // Lookup in master data
            var match = HsnMasterData.Lookup(hsnCode);
            if (match != null)
                return new HsnValidationResult(true, "Valid " + type, hsnCode, match.Description, match.GstRate, type);

            // Even if not in our master, format is valid
            return new HsnValidationResult(true, "Valid format (not in master database)", hsnCode, null, null, type);
        }

        /// <summary>
        /// Search HSN codes by keyword or partial code.
        /// Returns top 20 matches for auto-suggest.
        /// </summary>
        public static List<HsnEntry> SearchHsn(string query, int maxResults = 20)
        {
            return HsnMasterData.Search(query, maxResults);
        }

        /// <summary>
        /// Get GST rate for a given HSN code.
        /// Returns null if not found.
        /// </summary>
        public static double? GetGstRateForHsn(string hsnCode)
        {
            var entry = HsnMasterData.Lookup(hsnCode);
            return entry?.GstRate;
        }

        // ═══════════════════════════════════════════════════════════
        // STATE CODE MAPPING (Indian States & UTs)
        // ═══════════════════════════════════════════════════════════

        private static readonly Dictionary<int, string> StateMap = new()
        {
            { 1, "Jammu & Kashmir" }, { 2, "Himachal Pradesh" }, { 3, "Punjab" },
            { 4, "Chandigarh" }, { 5, "Uttarakhand" }, { 6, "Haryana" },
            { 7, "Delhi" }, { 8, "Rajasthan" }, { 9, "Uttar Pradesh" },
            { 10, "Bihar" }, { 11, "Sikkim" }, { 12, "Arunachal Pradesh" },
            { 13, "Nagaland" }, { 14, "Manipur" }, { 15, "Mizoram" },
            { 16, "Tripura" }, { 17, "Meghalaya" }, { 18, "Assam" },
            { 19, "West Bengal" }, { 20, "Jharkhand" }, { 21, "Odisha" },
            { 22, "Chattisgarh" }, { 23, "Madhya Pradesh" }, { 24, "Gujarat" },
            { 25, "Daman & Diu" }, { 26, "Dadra & Nagar Haveli" },
            { 27, "Maharashtra" }, { 28, "Andhra Pradesh (Old)" },
            { 29, "Karnataka" }, { 30, "Goa" }, { 31, "Lakshadweep" },
            { 32, "Kerala" }, { 33, "Tamil Nadu" }, { 34, "Puducherry" },
            { 35, "Andaman & Nicobar" }, { 36, "Telangana" },
            { 37, "Andhra Pradesh" },
        };

        public static string GetStateName(int stateCode)
            => StateMap.TryGetValue(stateCode, out var name) ? name : "Unknown";

        public static int GetStateCode(string stateName)
        {
            if (string.IsNullOrWhiteSpace(stateName)) return 0;
            var match = StateMap.FirstOrDefault(kv =>
                kv.Value.Equals(stateName, StringComparison.OrdinalIgnoreCase));
            return match.Key;
        }

        public static List<(int Code, string Name)> GetAllStates()
            => StateMap.Select(kv => (kv.Key, kv.Value)).OrderBy(x => x.Key).ToList();

        private static string GetPanEntityType(char c) => c switch
        {
            'C' => "Company",
            'P' => "Person",
            'H' => "HUF",
            'F' => "Firm",
            'A' => "AOP",
            'T' => "Trust",
            'B' => "BOI",
            'L' => "Local Authority",
            'J' => "Artificial Juridical Person",
            'G' => "Government",
            _ => "Other"
        };

        // ═══════════════════════════════════════════════════════════
        // GST CALCULATION HELPERS
        // ═══════════════════════════════════════════════════════════

        /// <summary>
        /// Calculate GST breakup for a given amount and rate.
        /// </summary>
        public static GstBreakup CalculateGst(double taxableAmount, double gstRatePercent, GstType type, bool isInclusive = false)
        {
            double baseAmount = taxableAmount;
            if (isInclusive)
                baseAmount = taxableAmount / (1 + gstRatePercent / 100);

            double totalTax = baseAmount * gstRatePercent / 100;

            if (type == GstType.InterState)
            {
                return new GstBreakup
                {
                    TaxableAmount = Math.Round(baseAmount, 2),
                    CGST = 0,
                    SGST = 0,
                    IGST = Math.Round(totalTax, 2),
                    TotalTax = Math.Round(totalTax, 2),
                    GrandTotal = Math.Round(baseAmount + totalTax, 2)
                };
            }
            else
            {
                double half = totalTax / 2;
                return new GstBreakup
                {
                    TaxableAmount = Math.Round(baseAmount, 2),
                    CGST = Math.Round(half, 2),
                    SGST = Math.Round(half, 2),
                    IGST = 0,
                    TotalTax = Math.Round(totalTax, 2),
                    GrandTotal = Math.Round(baseAmount + totalTax, 2)
                };
            }
        }
    }

    // ═══════════════════════════════════════════════════════════
    // RESULT TYPES
    // ═══════════════════════════════════════════════════════════

    public enum GstType { IntraState, InterState }

    public class GstinValidationResult
    {
        public bool IsValid { get; set; }
        public string Message { get; set; }
        public int StateCode { get; set; }
        public string StateName { get; set; } = "";
        public string Pan { get; set; } = "";
        public string EntityType { get; set; } = "";

        public GstinValidationResult(bool isValid, string message, int stateCode = 0,
            string stateName = "", string pan = "", string entityType = "")
        {
            IsValid = isValid; Message = message; StateCode = stateCode;
            StateName = stateName; Pan = pan; EntityType = entityType;
        }
    }

    public class HsnValidationResult
    {
        public bool IsValid { get; set; }
        public string Message { get; set; }
        public string Code { get; set; } = "";
        public string? Description { get; set; }
        public double? GstRate { get; set; }
        public string Type { get; set; } = "";

        public HsnValidationResult(bool isValid, string message, string code = "",
            string? description = null, double? gstRate = null, string type = "")
        {
            IsValid = isValid; Message = message; Code = code;
            Description = description; GstRate = gstRate; Type = type;
        }
    }

    public class GstBreakup
    {
        public double TaxableAmount { get; set; }
        public double CGST { get; set; }
        public double SGST { get; set; }
        public double IGST { get; set; }
        public double TotalTax { get; set; }
        public double GrandTotal { get; set; }
    }

    public class HsnEntry
    {
        public string Code { get; set; } = "";
        public string Description { get; set; } = "";
        public double GstRate { get; set; }
    }

    public class GstinOnlineResult
    {
        public bool IsValid { get; set; }
        public string Message { get; set; }
        public string LegalName { get; set; } = "";
        public string TradeName { get; set; } = "";
        public string Status { get; set; } = "";
        public string Address { get; set; } = "";
        public int StateCode { get; set; }
        public string StateName { get; set; } = "";

        public string DisplayName => !string.IsNullOrEmpty(TradeName) ? TradeName :
                                     !string.IsNullOrEmpty(LegalName) ? LegalName : "";

        public GstinOnlineResult(bool isValid, string message, string legalName = "",
            string tradeName = "", string status = "", string address = "",
            int stateCode = 0, string stateName = "")
        {
            IsValid = isValid; Message = message; LegalName = legalName;
            TradeName = tradeName; Status = status; Address = address;
            StateCode = stateCode; StateName = stateName;
        }
    }
}
