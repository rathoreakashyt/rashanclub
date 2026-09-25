using System;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// BusyNotify Import Service
    ///
    /// Flow:
    ///   1. POST /api/desktop/busy-import/customers  (~15s)
    ///   2. POST /api/desktop/busy-import/products   (~90s with batch CASE update)
    ///   3. forcePull → local SQLite update
    /// </summary>
    public class BusyImportService
    {
        private readonly ApiService _api;
        private readonly SyncService _sync;

        // Products: BusyNotify fetch ~25s + DB batch update ~70s = ~100s
        private const int ProductsTimeoutSeconds = 300;
        private const int CustomersTimeoutSeconds = 60;

        public BusyImportService(ApiService api, SyncService sync)
        {
            _api  = api;
            _sync = sync;
        }

        public event Action<string>? ProgressChanged;
        private void Report(string msg) => ProgressChanged?.Invoke(msg);

        // ─── Main ─────────────────────────────────────────────────────

        public async Task<BusyImportResult> ImportAllAsync()
        {
            var result = new BusyImportResult();
            try
            {
                // 1. Status check
                Report("BusyNotify server check ho raha hai...");
                var status = await GetStatusAsync();
                if (!status.Configured)
                {
                    result.Success = false;
                    result.Message = status.Message ?? "BusyNotify server pe configure nahi hai.";
                    return result;
                }
                Report($"Connected: {status.CompanyName}");

                // 2. Customers (fast ~15s)
                Report("Customers import ho rahe hain...");
                var custResult = await CallEndpointAsync("/api/desktop/busy-import/customers", CustomersTimeoutSeconds);
                result.CustomersImported = custResult.Imported;
                result.CustomersUpdated  = custResult.Updated;
                Report($"Customers done: {custResult.Imported} naye, {custResult.Updated} update");

                // 3. Products + Stock (slower ~90-300s)
                Report("Items + stock import ho rahe hain (11,000+ items)...\nPlease wait...");
                var prodResult = await CallEndpointAsync("/api/desktop/busy-import/products", ProductsTimeoutSeconds);
                result.ProductsImported = prodResult.Imported;
                result.ProductsUpdated  = prodResult.Updated;
                Report($"Items done: {prodResult.Imported} naye, {prodResult.Updated} update");

                if (!custResult.Success && !prodResult.Success)
                {
                    result.Success = false;
                    result.Message = "Import failed: " + (custResult.Message ?? prodResult.Message);
                    return result;
                }

                // 4. Pull to local SQLite
                Report("Local software sync ho raha hai...");
                await _sync.SyncNowAsync(silent: false, forcePull: true);

                result.Success = true;
                result.Message = $"Import done!\n" +
                                 $"• Customers: {result.CustomersImported} naye, {result.CustomersUpdated} update\n" +
                                 $"• Items: {result.ProductsImported} naye, {result.ProductsUpdated} update";
                Report("✅ Import complete!");

                // Dashboard stats (stock value / low stock) turant refresh karo
                StockEvents.NotifyStockChanged();
            }
            catch (TaskCanceledException)
            {
                result.Success = false;
                result.Message = "Timeout — server pe import chal raha hai.\nKuch der baad 'Resync' karo.";
                Report("⚠ Timeout — Resync karo kuch minutes mein");
            }
            catch (Exception ex)
            {
                result.Success = false;
                result.Message = "Error: " + ex.Message;
                LogService.Error("BusyImportService: " + ex.Message);
            }
            return result;
        }

        // ─── Helpers ─────────────────────────────────────────────────

        private async Task<BusyStatusInfo> GetStatusAsync()
        {
            try
            {
                var doc = await _api.GetDesktopReportJsonAsync("/api/desktop/busy-import/status");
                if (doc == null) return new BusyStatusInfo { Message = "Server unreachable" };
                var r = doc.RootElement;
                return new BusyStatusInfo
                {
                    Configured  = r.TryGetProperty("configured",   out var c)  && c.GetBoolean(),
                    CompanyName = r.TryGetProperty("company_name", out var cn) ? cn.GetString() : "Unknown",
                    Message     = r.TryGetProperty("message",      out var m)  ? m.GetString()  : null,
                };
            }
            catch { return new BusyStatusInfo { Message = "Status check failed" }; }
        }

        private async Task<EndpointResult> CallEndpointAsync(string path, int timeoutSeconds)
        {
            try
            {
                using var handler = new HttpClientHandler { ServerCertificateCustomValidationCallback = (_, _, _, _) => true };
                using var client  = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(timeoutSeconds) };
                var token = _api.Token;
                if (token != null)
                    client.DefaultRequestHeaders.Authorization =
                        new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", token);

                var content  = new StringContent("{}", Encoding.UTF8, "application/json");
                var url      = _api.BaseUrl.TrimEnd('/') + path;
                var response = await client.PostAsync(url, content);
                var body     = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                    return new EndpointResult { Message = $"Server {(int)response.StatusCode}" };

                using var doc = JsonDocument.Parse(body);
                var root      = doc.RootElement;
                var imported  = root.TryGetProperty("imported", out var i) ? i.GetInt32() : 0;
                var updated   = root.TryGetProperty("updated",  out var u) ? u.GetInt32() : 0;
                // summary fallback for /all endpoint
                if (root.TryGetProperty("summary", out var s))
                {
                    imported = s.TryGetProperty("products_imported", out var pi) ? pi.GetInt32() : imported;
                    updated  = s.TryGetProperty("products_updated",  out var pu) ? pu.GetInt32() : updated;
                }
                return new EndpointResult
                {
                    Success  = root.TryGetProperty("success", out var sc) && sc.GetBoolean(),
                    Imported = imported,
                    Updated  = updated,
                    Message  = root.TryGetProperty("message", out var msg) ? msg.GetString() ?? "" : "",
                };
            }
            catch (TaskCanceledException) { throw; }
            catch (Exception ex)
            {
                LogService.Warn($"BusyImport {path}: {ex.Message}");
                return new EndpointResult { Message = ex.Message };
            }
        }
    }

    // ─── DTOs ────────────────────────────────────────────────────────

    public class BusyImportResult
    {
        public bool   Success           { get; set; }
        public string Message           { get; set; } = "";
        public int    CustomersImported { get; set; }
        public int    CustomersUpdated  { get; set; }
        public int    ProductsImported  { get; set; }
        public int    ProductsUpdated   { get; set; }
    }

    internal class BusyStatusInfo
    {
        public bool    Configured  { get; set; }
        public string? CompanyName { get; set; }
        public string? Message     { get; set; }
    }

    internal class EndpointResult
    {
        public bool   Success  { get; set; }
        public int    Imported { get; set; }
        public int    Updated  { get; set; }
        public string Message  { get; set; } = "";
    }
}
