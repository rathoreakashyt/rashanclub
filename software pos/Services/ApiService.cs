using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text;
using System.Text.Json;
using Microsoft.Data.Sqlite;
using RashanKiDukan.Database;

namespace RashanKiDukan.Services
{
    /// <summary>
    /// Enterprise HTTP client for the Laravel (Sanctum) backend API.
    /// Features: HTTPS enforcement, exponential backoff retry, proper error handling, request batching.
    /// </summary>
    public class ApiService
    {
        private static readonly JsonSerializerOptions JsonOptions = new()
        {
            PropertyNamingPolicy = null,
            DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull
        };

        private readonly DatabaseService _db = new();
        private readonly object _clientLock = new();
        private readonly SemaphoreSlim _authLock = new(1, 1);
        private HttpClient? _client;
        private string _baseUrl = "";

        public DatabaseService Db => _db;

        // ═══ ENTERPRISE: Retry configuration ═══
        private const int MaxRetries = 5;
        private const int BaseDelayMs = 1000; // 1s, 2s, 4s, 8s exponential backoff
        private const int RequestTimeoutSeconds = 120;

        public string BaseUrl
        {
            get
            {
                if (string.IsNullOrEmpty(_baseUrl))
                {
                    var stored = GetSetting("server_url");
                    _baseUrl = !string.IsNullOrEmpty(stored) && IsSecureUrl(stored) ? stored : "http://localhost:8080";
                }
                return _baseUrl.TrimEnd('/');
            }
            set
            {
                ValidateBaseUrl(value);
                _baseUrl = value.TrimEnd('/');
                // Reset HttpClient so new handler picks up correct SSL settings for new host
                lock (_clientLock) { _client?.Dispose(); _client = null; }
            }
        }

        private static bool IsLoopbackHost(string host)
        {
            if (host == "localhost" || host == "127.0.0.1" || host == "::1") return true;
            // Private/LAN IPs — allowed for local testing (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
            if (System.Net.IPAddress.TryParse(host, out var ip))
            {
                byte[] b = ip.GetAddressBytes();
                if (b.Length == 4)
                {
                    if (b[0] == 10) return true;
                    if (b[0] == 192 && b[1] == 168) return true;
                    if (b[0] == 172 && b[1] >= 16 && b[1] <= 31) return true;
                }
            }
            return false;
        }

        private static bool IsSecureUrl(string url)
        {
            if (!Uri.TryCreate(url, UriKind.Absolute, out var uri)) return false;
            if (uri.Scheme == Uri.UriSchemeHttps) return true;
            if (uri.Scheme == Uri.UriSchemeHttp && IsLoopbackHost(uri.Host)) return true;
            return false;
        }

        private static void ValidateBaseUrl(string url)
        {
            if (!Uri.TryCreate(url, UriKind.Absolute, out var uri) || string.IsNullOrEmpty(uri.Host))
                throw new InvalidOperationException("Invalid server URL. Please enter a valid URL (e.g. https://api.example.com).");
            if (uri.Scheme == Uri.UriSchemeHttps) return;
            if (uri.Scheme == Uri.UriSchemeHttp && IsLoopbackHost(uri.Host)) return;
            throw new InvalidOperationException("Insecure URL: only https:// (or http://localhost / LAN IP) is allowed.");
        }

        private string? _cachedToken;

        public string? Token
        {
            get
            {
                if (_cachedToken != null) return _cachedToken;
                _cachedToken = SecureSettingsService.DecryptAndGet(_db, "server_token");
                return _cachedToken;
            }
            private set
            {
                _cachedToken = value;
                if (value != null)
                    SecureSettingsService.EncryptAndStore(_db, "server_token", value);
                else
                    SetSetting("server_token", null);
            }
        }

        public bool IsConfigured => !string.IsNullOrEmpty(BaseUrl);

        public void SetCredentials(string url, string email, string password)
        {
            BaseUrl = url;
            SetSetting("server_url", url.TrimEnd('/'));
            // Store credentials encrypted
            SecureSettingsService.EncryptAndStore(_db, "server_email", email);
            SecureSettingsService.EncryptAndStore(_db, "server_password", password);
            LogService.Info("Server credentials updated (encrypted)");
        }

        public void SaveToken(string? token)
        {
            _cachedToken = token;
            if (token != null)
                SecureSettingsService.EncryptAndStore(_db, "server_token", token);
            else
                SetSetting("server_token", null);
        }

        public void ClearToken()
        {
            _cachedToken = null;
            SetSetting("server_token", null);
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "DELETE FROM AppSettings WHERE Key = 'enc_server_token'";
                cmd.ExecuteNonQuery();
            }
            catch { }
        }

        private async Task<bool> EnsureAuthorizedAsync()
        {
            await _authLock.WaitAsync();
            try
            {
                var email = SecureSettingsService.DecryptAndGet(_db, "server_email");
                var password = SecureSettingsService.DecryptAndGet(_db, "server_password");
                if (string.IsNullOrEmpty(email) || string.IsNullOrEmpty(password)) return false;
                var (ok, _, _) = await LoginAsync(email, password);
                return ok;
            }
            finally
            {
                _authLock.Release();
            }
        }

        /// <summary>
        /// Public method for other services (e.g., GstValidationService) to trigger re-auth.
        /// </summary>
        public async Task<bool> EnsureTokenAsync()
        {
            ClearToken(); // Force re-login
            return await EnsureAuthorizedAsync();
        }

        private HttpClient Client()
        {
            lock (_clientLock)
            {
                if (_client == null)
                {
                    var handler = new HttpClientHandler();
                    if (IsLoopbackHost(new Uri(BaseUrl).Host))
                    {
                        handler.ServerCertificateCustomValidationCallback = (_, _, _, _) => true;
                    }
                    // ═══ ROOT CAUSE FIX (products sync timeout) ═══
                    // Bina gzip ke /api/sync/pull 12.7MB uncompressed bhejta hai —
                    // slow connection pe 148s lagta hai aur 120s timeout har baar fail
                    // hota tha (products kabhi sync nahi hote). Gzip me ~1MB / 12s.
                    handler.AutomaticDecompression = DecompressionMethods.All;
                    _client = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(RequestTimeoutSeconds) };
                    _client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                }

                int outletId = OutletContext.GetSelectedOutletId(_db);
                _client.DefaultRequestHeaders.Remove("X-Outlet-Id");
                _client.DefaultRequestHeaders.TryAddWithoutValidation("X-Outlet-Id", outletId.ToString());
                _client.DefaultRequestHeaders.Remove("X-Device-Id");
                _client.DefaultRequestHeaders.TryAddWithoutValidation("X-Device-Id", DeviceContext.GetDeviceId());

                var token = Token;
                _client.DefaultRequestHeaders.Authorization = !string.IsNullOrEmpty(token)
                    ? new AuthenticationHeaderValue("Bearer", token)
                    : null;

                return _client;
            }
        }

        /// <summary>
        /// Enterprise retry with exponential backoff. Retries on network errors and 5xx responses.
        /// Does NOT retry on 4xx (client errors) except 429 (rate limiting).
        /// </summary>
        private async Task<HttpResponseMessage> SendWithRetryAsync(Func<Task<HttpResponseMessage>> requestFunc, string operationName = "")
        {
            Exception? lastException = null;
            for (int attempt = 0; attempt < MaxRetries; attempt++)
            {
                try
                {
                    if (attempt > 0)
                    {
                        int delay = BaseDelayMs * (int)Math.Pow(2, attempt - 1);
                        // Add jitter to prevent thundering herd
                        delay += Random.Shared.Next(0, delay / 2);
                        LogService.Warn($"API retry {attempt}/{MaxRetries} for {operationName}, waiting {delay}ms");
                        await Task.Delay(delay);
                    }

                    var response = await requestFunc();

                    // Don't retry on client errors (except rate limiting)
                    if ((int)response.StatusCode >= 400 && (int)response.StatusCode < 500 && (int)response.StatusCode != 429)
                        return response;

                    // Retry on server errors and rate limiting
                    if ((int)response.StatusCode >= 500 || (int)response.StatusCode == 429)
                    {
                        if (attempt == MaxRetries - 1) return response;
                        continue;
                    }

                    return response;
                }
                catch (TaskCanceledException)
                {
                    lastException = new TimeoutException($"Request timed out: {operationName}");
                    LogService.Warn($"API timeout for {operationName}, attempt {attempt + 1}");
                }
                catch (HttpRequestException ex) when (attempt < MaxRetries - 1)
                {
                    lastException = ex;
                    LogService.Warn($"API network error for {operationName}: {ex.Message}");
                }
            }
            throw lastException ?? new HttpRequestException($"Request failed after {MaxRetries} retries: {operationName}");
        }

        // ═══════════ AUTH ═══════════

        public async Task<(bool ok, string message, string? token)> LoginAsync(string email, string password)
        {
            try
            {
                var payload = new { email, password };
                var response = await SendWithRetryAsync(
                    () => Client().PostAsJsonAsync($"{BaseUrl}/api/auth/login", payload),
                    "LoginAsync");
                var body = await response.Content.ReadAsStringAsync();

                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;

                if (response.IsSuccessStatusCode && root.TryGetProperty("token", out var tok))
                {
                    SaveToken(tok.GetString());
                    LogService.Info("API login successful");
                    return (true, "Connected", tok.GetString());
                }

                var msg = root.TryGetProperty("message", out var m) ? m.GetString() : "Login failed";
                LogService.Warn($"API login failed: {msg}");
                return (false, msg ?? "Login failed", null);
            }
            catch (Exception ex)
            {
                LogService.Error("API login exception", ex);
                return (false, ex.Message, null);
            }
        }

        public async Task<bool> HealthAsync()
        {
            try
            {
                var response = await SendWithRetryAsync(
                    () => Client().GetAsync($"{BaseUrl}/api/health"),
                    "HealthAsync");
                if (!response.IsSuccessStatusCode)
                    LogService.Warn($"Health check failed ({response.StatusCode})");
                return response.IsSuccessStatusCode;
            }
            catch (Exception ex)
            {
                LogService.Warn($"Health check exception: {ex.Message}");
                return false;
            }
        }

        // ═══════════ SYNC ═══════════

        public async Task<(bool ok, string message, JsonDocument? data)> PullAsync(string? since)
        {
            string url = $"{BaseUrl}/api/sync/pull";
            if (!string.IsNullOrEmpty(since)) url += $"?since={Uri.EscapeDataString(since)}";

            var (ok, message, data, statusCode) = await PullOnceAsync(url);
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                (ok, message, data, statusCode) = await PullOnceAsync(url);
            }
            return (ok, message, data);
        }

        private async Task<(bool ok, string message, JsonDocument? data, HttpStatusCode statusCode)> PullOnceAsync(string url)
        {
            try
            {
                var response = await SendWithRetryAsync(
                    () => Client().GetAsync(url),
                    $"Pull:{url.Split('?')[0]}");
                var body = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                {
                    LogService.Warn($"Pull failed ({response.StatusCode}): {url}");
                    // Don't include HTML body in user-facing message
                    string shortErr = body.Contains("<html", StringComparison.OrdinalIgnoreCase)
                        ? $"Server error ({(int)response.StatusCode})"
                        : body.Length > 100 ? body.Substring(0, 100) : body;
                    return (false, $"Pull failed ({response.StatusCode}): {shortErr}", null, response.StatusCode);
                }

                return (true, "OK", JsonDocument.Parse(body), response.StatusCode);
            }
            catch (Exception ex)
            {
                LogService.Error($"Pull exception: {url}", ex);
                return (false, ex.Message, null, HttpStatusCode.InternalServerError);
            }
        }

        public async Task<(bool ok, string message, JsonDocument? data)> GetMetaAsync()
        {
            var (ok, message, data, statusCode) = await PullOnceAsync($"{BaseUrl}/api/meta");
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                (ok, message, data, statusCode) = await PullOnceAsync($"{BaseUrl}/api/meta");
            }
            return (ok, message, data);
        }

        public async Task<(bool ok, string message, JsonDocument? data)> PushAsync(object payload)
        {
            var json = JsonSerializer.Serialize(payload, JsonOptions);
            LogService.Info($"Push payload size: {json.Length} bytes");

            var (ok, message, data, statusCode) = await PushOnceAsync(json);
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                (ok, message, data, statusCode) = await PushOnceAsync(json);
            }
            return (ok, message, data);
        }

        /// <summary>
        /// FIX 3: Per-entity-type split push (one request = one entity type).
        /// Main /push fail ho (server-side error) to desktop is endpoint se
        /// entity-by-entity push karta hai taaki ek entity ki failure baaki
        /// entities ke sync ko block na kare.
        /// </summary>
        public async Task<(bool ok, string message, JsonDocument? data)> PushBatchAsync(string entityType, object records)
        {
            var payload = new Dictionary<string, object> { ["entity_type"] = entityType, ["records"] = records };
            var json = JsonSerializer.Serialize(payload, JsonOptions);
            var content = new StringContent(json, Encoding.UTF8, "application/json");
            content.Headers.TryAddWithoutValidation("X-Idempotency-Key", ComputeIdempotencyKey(json));

            var (ok, message, data, statusCode) = await PushBatchOnceAsync(content);
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                content = new StringContent(json, Encoding.UTF8, "application/json");
                content.Headers.TryAddWithoutValidation("X-Idempotency-Key", ComputeIdempotencyKey(json));
                (ok, message, data, statusCode) = await PushBatchOnceAsync(content);
            }
            return (ok, message, data);
        }

        private async Task<(bool ok, string message, JsonDocument? data, HttpStatusCode statusCode)> PushBatchOnceAsync(HttpContent content)
        {
            try
            {
                var response = await SendWithRetryAsync(
                    () => Client().PostAsync($"{BaseUrl}/api/sync/push/batch", content),
                    "PushBatch");
                var body = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                {
                    LogService.Warn($"PushBatch failed ({response.StatusCode}): {body[..Math.Min(body.Length, 500)]}");
                    return (false, $"Push failed ({response.StatusCode}): {body}", null, response.StatusCode);
                }

                return (true, "OK", JsonDocument.Parse(body), response.StatusCode);
            }
            catch (Exception ex)
            {
                LogService.Error("PushBatch exception", ex);
                return (false, ex.Message, null, HttpStatusCode.InternalServerError);
            }
        }

        /// <summary>
        /// FIX 4: Unresolved dead letters server se fetch karo (silent sync drops
        /// ka pata chalta hai — promotions jaise records jo validation mein atak gaye).
        /// </summary>
        public async Task<(bool ok, string message, JsonDocument? data)> GetDeadLettersAsync()
        {
            var (ok, message, data, statusCode) = await PullOnceAsync($"{BaseUrl}/api/sync/dead-letters");
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                (ok, message, data, statusCode) = await PullOnceAsync($"{BaseUrl}/api/sync/dead-letters");
            }
            return (ok, message, data);
        }

        /// <summary>
        /// FIX 1: Har push payload ka SHA256 — server is key se duplicate request
        /// detect karke wahi response wapas karta hai (ack lost / network timeout
        /// ke baad dobara push hone par duplicate insert nahi hota).
        /// </summary>
        private static string ComputeIdempotencyKey(string json)
        {
            var bytes = System.Security.Cryptography.SHA256.HashData(Encoding.UTF8.GetBytes(json));
            return Convert.ToHexString(bytes).ToLowerInvariant();
        }

        /// <summary>
        /// Push one generic entity (entity_type/entity_id/operation/payload) to the
        /// dedicated /api/sync/push-entity endpoint. Used by the offline pending_sync
        /// queue for config/CRUD entities the typed push endpoint does not accept.
        /// </summary>
        public async Task<(bool ok, string message, JsonDocument? data)> PushEntityAsync(object payload)
        {
            var json = JsonSerializer.Serialize(payload, JsonOptions);
            var content = new StringContent(json, Encoding.UTF8, "application/json");
            content.Headers.TryAddWithoutValidation("X-Idempotency-Key", ComputeIdempotencyKey(json));

            var (ok, message, data, statusCode) = await PushEntityOnceAsync(content);
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                content = new StringContent(json, Encoding.UTF8, "application/json");
                content.Headers.TryAddWithoutValidation("X-Idempotency-Key", ComputeIdempotencyKey(json));
                (ok, message, data, statusCode) = await PushEntityOnceAsync(content);
            }
            return (ok, message, data);
        }

        private async Task<(bool ok, string message, JsonDocument? data, HttpStatusCode statusCode)> PushEntityOnceAsync(HttpContent content)
        {
            try
            {
                var response = await SendWithRetryAsync(
                    () => Client().PostAsync($"{BaseUrl}/api/sync/push-entity", content),
                    "PushEntity");
                var body = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                {
                    LogService.Warn($"PushEntity failed ({response.StatusCode})");
                    return (false, $"Push failed ({response.StatusCode}): {body}", null, response.StatusCode);
                }

                return (true, "OK", JsonDocument.Parse(body), response.StatusCode);
            }
            catch (Exception ex)
            {
                LogService.Error("PushEntity exception", ex);
                return (false, ex.Message, null, HttpStatusCode.InternalServerError);
            }
        }

        private async Task<(bool ok, string message, JsonDocument? data, HttpStatusCode statusCode)> PushOnceAsync(string json)
        {
            try
            {
                var content = new StringContent(json, Encoding.UTF8, "application/json");
                content.Headers.TryAddWithoutValidation("X-Idempotency-Key", ComputeIdempotencyKey(json));
                var response = await SendWithRetryAsync(
                    () => Client().PostAsync($"{BaseUrl}/api/sync/push", content),
                    "Push");
                var body = await response.Content.ReadAsStringAsync();

                if (!response.IsSuccessStatusCode)
                {
                    LogService.Warn($"Push failed ({response.StatusCode}): {body[..Math.Min(body.Length, 500)]}");
                    return (false, $"Push failed ({response.StatusCode}): {body}", null, response.StatusCode);
                }

                return (true, "OK", JsonDocument.Parse(body), response.StatusCode);
            }
            catch (Exception ex)
            {
                LogService.Error("Push exception", ex);
                return (false, ex.Message, null, HttpStatusCode.InternalServerError);
            }
        }

        public async Task<(bool ok, string message, JsonDocument? data)> GetSendBillSettingsAsync()
        {
            var (ok, message, data, statusCode) = await PullOnceAsync($"{BaseUrl}/api/send-bill/settings");
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
                (ok, message, data, statusCode) = await PullOnceAsync($"{BaseUrl}/api/send-bill/settings");
            return (ok, message, data);
        }

        public async Task<(bool ok, string message)> SendBillAsync(string channel, string to, string subject, string message)
        {
            var payload = new { channel, to, subject, message };
            var json = JsonSerializer.Serialize(payload, JsonOptions);
            var content = new StringContent(json, Encoding.UTF8, "application/json");

            var (ok, msg, data, statusCode) = await SendBillOnceAsync(content);
            if (statusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
            {
                content = new StringContent(json, Encoding.UTF8, "application/json");
                (ok, msg, data, statusCode) = await SendBillOnceAsync(content);
            }
            return (ok, msg);
        }

        private async Task<(bool ok, string message, JsonDocument? data, HttpStatusCode statusCode)> SendBillOnceAsync(HttpContent content)
        {
            try
            {
                var response = await SendWithRetryAsync(
                    () => Client().PostAsync($"{BaseUrl}/api/send-bill", content),
                    "SendBill");
                var body = await response.Content.ReadAsStringAsync();

                string? serverMsg = null;
                try
                {
                    using var doc = JsonDocument.Parse(body);
                    if (doc.RootElement.TryGetProperty("message", out var m))
                        serverMsg = m.GetString();
                }
                catch { }

                if (!response.IsSuccessStatusCode)
                {
                    LogService.Warn($"SendBill failed ({response.StatusCode}): {serverMsg}");
                    return (false, serverMsg ?? $"Send failed ({response.StatusCode})", null, response.StatusCode);
                }

                return (true, serverMsg ?? "Sent", null, response.StatusCode);
            }
            catch (Exception ex)
            {
                LogService.Warn($"SendBill exception: {ex.Message}");
                return (false, ex.Message, null, HttpStatusCode.InternalServerError);
            }
        }

        public async Task<(bool ok, string message)> TestLoginAsync(string url, string email, string password)
        {
            try
            {
                if (!IsSecureUrl(url))
                    return (false, "Insecure URL: only https:// (or http://localhost) is allowed.");

                using var temp = new HttpClient { Timeout = TimeSpan.FromSeconds(15) };
                temp.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));
                var payload = new { email, password };
                var response = await temp.PostAsJsonAsync($"{url.TrimEnd('/')}/api/auth/login", payload);
                var body = await response.Content.ReadAsStringAsync();

                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;

                if (response.IsSuccessStatusCode && root.TryGetProperty("token", out _))
                    return (true, "Connected successfully.");

                var msg = root.TryGetProperty("message", out var m) ? m.GetString() : "Login failed";
                return (false, msg ?? "Login failed");
            }
            catch (Exception ex)
            {
                return (false, ex.Message);
            }
        }

        // ═══════════ SETTINGS (SQLite helpers) ═══════════

        public string? GetSetting(string key)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                cmd.CommandText = "SELECT Value FROM AppSettings WHERE Key = @k";
                cmd.Parameters.AddWithValue("@k", key);
                var val = cmd.ExecuteScalar();
                return val as string;
            }
            catch { return null; }
        }

        public void SetSetting(string key, string? value)
        {
            try
            {
                using var conn = _db.GetConnection();
                using var cmd = conn.CreateCommand();
                if (value == null)
                {
                    cmd.CommandText = "DELETE FROM AppSettings WHERE Key = @k";
                    cmd.Parameters.AddWithValue("@k", key);
                    cmd.ExecuteNonQuery();
                    return;
                }
                cmd.CommandText = @"INSERT INTO AppSettings (Key, Value) VALUES (@k, @v)
                                    ON CONFLICT(Key) DO UPDATE SET Value = @v";
                cmd.Parameters.AddWithValue("@k", key);
                cmd.Parameters.AddWithValue("@v", value);
                cmd.ExecuteNonQuery();
            }
            catch { }
        }
        public async Task<List<CloudAccountBalance>?> GetAccountBalanceAsync()
        {
            try
            {
                var response = await Client().GetAsync($"{BaseUrl}/api/desktop/account-balance");
                var body = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (response.IsSuccessStatusCode && root.TryGetProperty("data", out var data) && data.TryGetProperty("accounts", out var accounts))
                {
                    var list = new List<CloudAccountBalance>();
                    foreach (var item in accounts.EnumerateArray())
                    {
                        list.Add(new CloudAccountBalance
                        {
                            AccountName = item.TryGetProperty("account_name", out var an) ? JsonStr(an) : "",
                            Balance = item.TryGetProperty("balance", out var bal) ? JsonStr(bal) : "0"
                        });
                    }
                    return list;
                }
                LogService.Warn($"GetAccountBalance failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetAccountBalance exception: {ex.Message}");
                return null;
            }
        }

        public async Task<List<CloudAccountStatement>?> GetAccountStatementAsync(long paymentMethodId, string dateFrom, string dateTo)
        {
            try
            {
                var url = BuildQueryUrl($"{BaseUrl}/api/desktop/account-statement",
                    ("payment_method_id", paymentMethodId.ToString()), ("date_from", dateFrom), ("date_to", dateTo));
                var response = await Client().GetAsync(url);
                var body = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (response.IsSuccessStatusCode && root.TryGetProperty("data", out var data) && data.TryGetProperty("statements", out var statements))
                {
                    var list = new List<CloudAccountStatement>();
                    foreach (var item in statements.EnumerateArray())
                    {
                        list.Add(new CloudAccountStatement
                        {
                            Sn = item.TryGetProperty("sn", out var sn) ? sn.GetInt32() : 0,
                            Date = item.TryGetProperty("date", out var d) ? JsonStr(d) : "",
                            Title = item.TryGetProperty("title", out var t) ? JsonStr(t) : "",
                            Debit = item.TryGetProperty("debit", out var dr) ? JsonStr(dr) : "",
                            Credit = item.TryGetProperty("credit", out var cr) ? JsonStr(cr) : "",
                            Balance = item.TryGetProperty("balance", out var b) ? JsonStr(b) : "",
                            AddedDateTime = item.TryGetProperty("added_date_time", out var adt) ? JsonStr(adt) : ""
                        });
                    }
                    return list;
                }
                LogService.Warn($"GetAccountStatement failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetAccountStatement exception: {ex.Message}");
                return null;
            }
        }

        public async Task<List<CloudTransactionHistory>?> GetTransactionHistoryAsync(long paymentMethodId, string dateFrom, string dateTo)
        {
            try
            {
                var url = BuildQueryUrl($"{BaseUrl}/api/desktop/transaction-history",
                    ("payment_method_id", paymentMethodId.ToString()), ("date_from", dateFrom), ("date_to", dateTo));

                var response = await Client().GetAsync(url);
                var body = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (response.IsSuccessStatusCode && root.TryGetProperty("data", out var data) && data.TryGetProperty("transactions", out var txns))
                {
                    var list = new List<CloudTransactionHistory>();
                    foreach (var item in txns.EnumerateArray())
                    {
                        list.Add(new CloudTransactionHistory
                        {
                            Date = item.TryGetProperty("date", out var d) ? JsonStr(d) : "",
                            ReferenceNo = item.TryGetProperty("reference_no", out var r) ? JsonStr(r) : "",
                            Type = item.TryGetProperty("type", out var t) ? JsonStr(t) : "",
                            PaymentMethod = item.TryGetProperty("payment_method", out var pm) ? JsonStr(pm) : "",
                            Amount = item.TryGetProperty("amount", out var a) ? JsonStr(a) : "",
                            CreatedAt = item.TryGetProperty("created_at", out var c) ? JsonStr(c) : ""
                        });
                    }
                    return list;
                }
                LogService.Warn($"GetTransactionHistory failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetTransactionHistory exception: {ex.Message}");
                return null;
            }
        }

        public async Task<CloudTrialBalanceResult?> GetTrialBalanceAsync(string dateFrom, string dateTo)
        {
            try
            {
                var url = BuildQueryUrl($"{BaseUrl}/api/desktop/trial-balance",
                    ("date_from", dateFrom), ("date_to", dateTo));

                var response = await Client().GetAsync(url);
                var body = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (response.IsSuccessStatusCode && root.TryGetProperty("data", out var data) && data.TryGetProperty("trialBalance", out var tb))
                {
                    var result = new CloudTrialBalanceResult();
                    foreach (var item in tb.EnumerateArray())
                    {
                        result.Rows.Add(new CloudTrialBalance
                        {
                            Sn = item.TryGetProperty("sn", out var sn) ? sn.GetInt32() : 0,
                            Title = item.TryGetProperty("title", out var t) ? JsonStr(t) : "",
                            Debit = item.TryGetProperty("debit", out var dr) ? JsonStr(dr) : "",
                            Credit = item.TryGetProperty("credit", out var cr) ? JsonStr(cr) : ""
                        });
                    }
                    if (data.TryGetProperty("summary", out var summary))
                    {
                        result.TotalDebit = summary.TryGetProperty("totalDebit", out var td) ? JsonStr(td) : "0";
                        result.TotalCredit = summary.TryGetProperty("totalCredit", out var tc) ? JsonStr(tc) : "0";
                    }
                    return result;
                }
                LogService.Warn($"GetTrialBalance failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetTrialBalance exception: {ex.Message}");
                return null;
            }
        }

        public async Task<CloudBalanceSheetResult?> GetBalanceSheetAsync()
        {
            try
            {
                var response = await Client().GetAsync($"{BaseUrl}/api/desktop/balance-sheet");
                var body = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (response.IsSuccessStatusCode && root.TryGetProperty("data", out var data))
                {
                    var result = new CloudBalanceSheetResult();
                    if (data.TryGetProperty("assets", out var assets))
                    {
                        foreach (var item in assets.EnumerateArray())
                            result.Assets.Add(new CloudBalanceSheetItem { Sn = item.TryGetProperty("sn", out var sn) ? sn.GetInt32() : 0, Title = item.TryGetProperty("title", out var t) ? JsonStr(t) : "", Amount = item.TryGetProperty("amount", out var a) ? JsonStr(a) : "" });
                    }
                    if (data.TryGetProperty("liabilities", out var liab))
                    {
                        foreach (var item in liab.EnumerateArray())
                            result.Liabilities.Add(new CloudBalanceSheetItem { Sn = item.TryGetProperty("sn", out var sn) ? sn.GetInt32() : 0, Title = item.TryGetProperty("title", out var t) ? JsonStr(t) : "", Amount = item.TryGetProperty("amount", out var a) ? JsonStr(a) : "" });
                    }
                    if (data.TryGetProperty("summary", out var summary))
                    {
                        result.TotalAssets = summary.TryGetProperty("totalAssets", out var ta) ? JsonStr(ta) : "0";
                        result.TotalLiabilities = summary.TryGetProperty("totalLiabilities", out var tl) ? JsonStr(tl) : "0";
                        result.NetWorth = summary.TryGetProperty("netWorth", out var nw) ? JsonStr(nw) : "0";
                    }
                    return result;
                }
                LogService.Warn($"GetBalanceSheet failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetBalanceSheet exception: {ex.Message}");
                return null;
            }
        }

        // ═══════════ PERMISSIONS (cloud) ═══════════

        public async Task<List<CloudPermission>?> GetPermissionsAsync()
        {
            try
            {
                var response = await Client().GetAsync($"{BaseUrl}/api/desktop/permissions");
                var body = await response.Content.ReadAsStringAsync();
                using var doc = JsonDocument.Parse(body);
                var root = doc.RootElement;
                if (response.IsSuccessStatusCode && root.TryGetProperty("data", out var data) && data.TryGetProperty("permissions", out var perms))
                {
                    var list = new List<CloudPermission>();
                    foreach (var item in perms.EnumerateArray())
                    {
                        list.Add(new CloudPermission
                        {
                            Id = item.TryGetProperty("id", out var id) ? (id.ValueKind == JsonValueKind.Number ? id.GetInt64() : 0) : 0,
                            Name = item.TryGetProperty("name", out var n) ? n.GetString() ?? "" : "",
                            GroupName = item.TryGetProperty("group_name", out var g) ? g.GetString() ?? "" : ""
                        });
                    }
                    return list;
                }
                // Fallback: try direct array (some API versions return flat array)
                if (response.IsSuccessStatusCode && root.ValueKind == JsonValueKind.Array)
                {
                    var list = new List<CloudPermission>();
                    foreach (var item in root.EnumerateArray())
                    {
                        list.Add(new CloudPermission
                        {
                            Id = item.TryGetProperty("id", out var id) ? (id.ValueKind == JsonValueKind.Number ? id.GetInt64() : 0) : 0,
                            Name = item.TryGetProperty("name", out var n) ? n.GetString() ?? "" : "",
                            GroupName = item.TryGetProperty("group_name", out var g) ? g.GetString() ?? "" : ""
                        });
                    }
                    return list;
                }
                LogService.Warn($"GetPermissions failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetPermissions exception: {ex.Message}");
                return null;
            }
        }

        public async Task<bool> PushRolesAsync(List<object> roles)
        {
            try
            {
                var payload = new { roles };
                var json = JsonSerializer.Serialize(payload, JsonOptions);
                var content = new StringContent(json, Encoding.UTF8, "application/json");
                var response = await Client().PostAsync($"{BaseUrl}/api/desktop/roles", content);
                if (!response.IsSuccessStatusCode)
                    LogService.Warn($"PushRoles failed ({response.StatusCode})");
                return response.IsSuccessStatusCode;
            }
            catch (Exception ex)
            {
                LogService.Warn($"PushRoles exception: {ex.Message}");
                return false;
            }
        }

        // ═══════════ CLOUD REPORT APIs ═══════════

        public async Task<JsonDocument?> GetCloudSalesReportAsync(string dateFrom, string dateTo, string? outletId = null, string? customerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/sales-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId), ("customer_id", customerId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudPurchaseReportAsync(string dateFrom, string dateTo, string? outletId = null, string? supplierId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/purchase-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId), ("supplier_id", supplierId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudStockReportAsync(string? categoryId = null, string? brandId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/stock-report",
                ("category_id", categoryId), ("brand_id", brandId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudExpenseReportAsync(string dateFrom, string dateTo, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/expense-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudIncomeReportAsync(string dateFrom, string dateTo, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/income-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudDueReportAsync(string type = "customer")
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/due-report?type={Uri.EscapeDataString(type)}");
        }

        public async Task<JsonDocument?> GetCloudEmployeeSaleReportAsync(string dateFrom, string dateTo, string? employeeId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/employee-sale-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("employee_id", employeeId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudZReportAsync(string date, string outletId)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/z-report?date={Uri.EscapeDataString(date)}&outlet_id={Uri.EscapeDataString(outletId)}");
        }

        public async Task<JsonDocument?> GetCloudDailySummaryAsync(string date, string outletId)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/daily-summary?date={Uri.EscapeDataString(date)}&outlet_id={Uri.EscapeDataString(outletId)}");
        }

        public async Task<JsonDocument?> GetCloudDashboardAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/dashboard");
        }

        /// <summary>Cloud company header info (name/email/phone/address/GSTIN/invoice logo) —
        /// desktop invoice PDF header cloud jaisa banane ke liye.</summary>
        public async Task<JsonDocument?> GetCompanyProfileAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/company-profile");
        }

        public async Task<(bool ok, string message, JsonDocument? data)> GetFeatureActivationsAsync()
        {
            var doc = await GetDesktopJsonAsync($"{BaseUrl}/api/feature-activations");
            return doc != null ? (true, "ok", doc) : (false, "Failed to load feature activations", null);
        }

        public async Task<(bool ok, string message)> UpdateFeatureActivationsAsync(List<object> features)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/feature-activations", new { features });
        }

        /// <summary>Public upload file download (e.g. invoice logo) — bina auth ke
        /// public/uploads se serve hota hai.</summary>
        public async Task<byte[]?> DownloadFileAsync(string relativeUrl)
        {
            try
            {
                using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(30) };
                return await client.GetByteArrayAsync($"{BaseUrl}/{relativeUrl.TrimStart('/')}");
            }
            catch (Exception ex)
            {
                LogService.Warn($"DownloadFile failed: {relativeUrl} — {ex.Message}");
                return null;
            }
        }

        // ═══════════ MARKETING APIs ═══════════

        public async Task<(bool ok, string message)> SendCloudSmsAsync(string to, string message)
        {
            var payload = new { to, message };
            return await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/marketing/sms", payload);
        }

        public async Task<(bool ok, string message)> SendCloudWhatsAppAsync(string to, string message)
        {
            var payload = new { to, message };
            return await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/marketing/whatsapp", payload);
        }

        public async Task<(bool ok, string message)> SendCloudEmailAsync(string to, string subject, string message)
        {
            var payload = new { to, subject, message };
            return await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/marketing/email", payload);
        }

        public async Task<(bool ok, string message, int sent)> SendCloudBulkSmsAsync(List<string> numbers, string message)
        {
            var payload = new { numbers, message };
            var (ok, msg) = await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/marketing/bulk-sms", payload);
            return (ok, msg, 0);
        }

        public async Task<JsonDocument?> GetCloudMarketingLogsAsync(string? channel = null, string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/marketing/logs",
                ("channel", channel), ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ ITEM IMPORT / BULK UPDATE APIs ═══════════

        public async Task<(bool ok, string message, int imported, int skipped)> CloudImportItemsAsync(List<object> items)
        {
            var payload = new { items };
            var (ok, msg) = await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/items/import", payload);
            return (ok, msg, 0, 0);
        }

        public async Task<(bool ok, string message, int updated, int notFound)> CloudBulkUpdateItemsAsync(List<object> updates)
        {
            var payload = new { updates };
            var (ok, msg) = await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/items/bulk-update", payload);
            return (ok, msg, 0, 0);
        }

        // ═══════════ DETAILED REPORT APIs ═══════════

        public async Task<JsonDocument?> GetCloudDetailedSaleReportAsync(string dateFrom, string dateTo, string? outletId = null, string? customerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/detailed-sale-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId), ("customer_id", customerId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudItemTrackingReportAsync(string itemId, string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/item-tracking-report",
                ("item_id", itemId), ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudPriceHistoryReportAsync(string itemId)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/price-history-report?item_id={Uri.EscapeDataString(itemId)}");
        }

        public async Task<JsonDocument?> GetCloudDetailedCashFlowReportAsync(string dateFrom, string dateTo, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/detailed-cash-flow-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudLoyaltyPointReportAsync(string type = "available")
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/loyalty-point-report?type={Uri.EscapeDataString(type)}");
        }

        public async Task<JsonDocument?> GetCloudSchemeReportAsync(string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/scheme-report",
                ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ BARCODE PRINT API ═══════════

        public async Task<JsonDocument?> GetCloudBarcodeDataAsync(string itemId, int copies = 1)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/barcode-data?item_id={Uri.EscapeDataString(itemId)}&copies={copies}");
        }

        // ═══════════ DELETE APIs ═══════════

        public async Task<(bool ok, string message)> CloudDeleteSaleAsync(string id)
        {
            return await DeleteDesktopAsync($"{BaseUrl}/api/desktop/sales/{Uri.EscapeDataString(id)}");
        }

        public async Task<(bool ok, string message)> CloudDeletePurchaseAsync(string id)
        {
            return await DeleteDesktopAsync($"{BaseUrl}/api/desktop/purchases/{Uri.EscapeDataString(id)}");
        }

        public async Task<(bool ok, string message)> CloudDeleteSaleReturnAsync(string id)
        {
            return await DeleteDesktopAsync($"{BaseUrl}/api/desktop/sale-returns/{Uri.EscapeDataString(id)}");
        }

        public async Task<(bool ok, string message)> CloudDeletePurchaseReturnAsync(string id)
        {
            return await DeleteDesktopAsync($"{BaseUrl}/api/desktop/purchase-returns/{Uri.EscapeDataString(id)}");
        }

        public async Task<(bool ok, string message)> CloudDeleteExpenseAsync(string id)
        {
            return await DeleteDesktopAsync($"{BaseUrl}/api/desktop/expenses/{Uri.EscapeDataString(id)}");
        }

        public async Task<(bool ok, string message)> CloudDeleteIncomeAsync(string id)
        {
            return await DeleteDesktopAsync($"{BaseUrl}/api/desktop/incomes/{Uri.EscapeDataString(id)}");
        }

        // ═══════════ SHOW / DETAIL APIs ═══════════

        public async Task<JsonDocument?> GetCloudSaleDetailAsync(string id)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/sales/{Uri.EscapeDataString(id)}");
        }

        public async Task<JsonDocument?> GetCloudPurchaseDetailAsync(string id)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/purchases/{Uri.EscapeDataString(id)}");
        }

        public async Task<JsonDocument?> GetCloudCustomerDetailAsync(string id)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/customers/{Uri.EscapeDataString(id)}");
        }

        public async Task<JsonDocument?> GetCloudSupplierDetailAsync(string id)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/suppliers/{Uri.EscapeDataString(id)}");
        }

        // ═══════════ STOCK SEGMENTATION API ═══════════

        public async Task<JsonDocument?> GetCloudStockSegmentationAsync(string itemId, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/stock-segmentation",
                ("item_id", itemId), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ WALLET TRANSACTIONS API ═══════════

        public async Task<JsonDocument?> GetCloudWalletTransactionsAsync(string? customerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/wallet/transactions",
                ("customer_id", customerId));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ OVERDUE INSTALLMENT APIs ═══════════

        public async Task<JsonDocument?> GetCloudOverdueInstallmentsAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/installment/overdue");
        }

        public async Task<(bool ok, string message, int sent)> CloudSendInstallmentNotificationsAsync(string type = "sms")
        {
            var payload = new { type };
            var (ok, msg) = await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/installment/send-notifications", payload);
            return (ok, msg, 0);
        }

        // ═══════════ TIER PRICING / SCHEME PERCENT APIs ═══════════

        public async Task<JsonDocument?> GetCloudTierPricingAsync(string itemId)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/tier-pricing?item_id={Uri.EscapeDataString(itemId)}");
        }

        public async Task<JsonDocument?> GetCloudSchemePercentAsync(string itemId)
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/scheme-percent?item_id={Uri.EscapeDataString(itemId)}");
        }

        // ═══════════ PAYMENT SETTLEMENT API ═══════════

        public async Task<JsonDocument?> GetCloudPaymentSettlementAsync(string date, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/payment-settlement",
                ("date", date), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ EDIT APIs ═══════════

        public async Task<(bool ok, string message)> CloudEditSaleAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/sales/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditPurchaseAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/purchases/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditSaleReturnAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/sale-returns/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditPurchaseReturnAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/purchase-returns/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditExpenseAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/expenses/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditIncomeAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/incomes/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditCustomerAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/customers/{Uri.EscapeDataString(id)}", fields);
        }

        public async Task<(bool ok, string message)> CloudEditSupplierAsync(string id, Dictionary<string, object> fields)
        {
            return await PutDesktopJsonAsync($"{BaseUrl}/api/desktop/suppliers/{Uri.EscapeDataString(id)}", fields);
        }

        // ═══════════ CUSTOMER DISPLAY API ═══════════

        public async Task<(bool ok, string message)> SendCustomerDisplayAsync(object displayData)
        {
            return await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/customer-display", displayData);
        }

        public async Task<JsonDocument?> GetCustomerDisplayAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/customer-display");
        }

        // ═══════════ CALCULATOR API ═══════════

        public async Task<JsonDocument?> CloudCalculateAsync(string expression)
        {
            var payload = new { expression };
            var (ok, msg) = await PostDesktopJsonAsync($"{BaseUrl}/api/desktop/calculator", payload);
            if (ok)
            {
                try { return JsonDocument.Parse(msg); } catch { }
            }
            return null;
        }

        // ═══════════ BARCODE PRINT API ═══════════

        public async Task<string?> GetCloudBarcodePrintHtmlAsync(string itemId, int copies = 1, int paperWidth = 70, int paperHeight = 30)
        {
            try
            {
                var url = $"{BaseUrl}/api/desktop/barcode-print?item_id={Uri.EscapeDataString(itemId)}&copies={copies}&paper_width={paperWidth}&paper_height={paperHeight}";
                var response = await SendWithRetryAsync(() => Client().GetAsync(url), "BarcodePrint");
                if (!response.IsSuccessStatusCode && response.StatusCode == HttpStatusCode.Unauthorized)
                {
                    if (await EnsureAuthorizedAsync())
                        response = await SendWithRetryAsync(() => Client().GetAsync(url), "BarcodePrint-retry");
                }
                if (response.IsSuccessStatusCode)
                    return await response.Content.ReadAsStringAsync();
                LogService.Warn($"GetCloudBarcodePrintHtml failed ({response.StatusCode})");
                return null;
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetCloudBarcodePrintHtml exception: {ex.Message}");
                return null;
            }
        }

        // ═══════════ 26 MISSING REPORT APIs ═══════════

        public async Task<JsonDocument?> GetCloudRegisterReportAsync(string date, string outletId, string? registerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/register-report",
                ("date", date), ("outlet_id", outletId), ("register_id", registerId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudFinalInvoiceDueReportAsync(string? dateFrom = null, string? dateTo = null, string? customerId = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/final-invoice-due-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("customer_id", customerId), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudServiceSaleReportAsync(string? dateFrom = null, string? dateTo = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/service-sale-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudComboServiceReportAsync(string? dateFrom = null, string? dateTo = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/combo-service-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudProductSaleReportAsync(string? dateFrom = null, string? dateTo = null, string? itemId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/product-sale-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("item_id", itemId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudProductProfitReportAsync(string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/product-profit-report",
                ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudTaxReportAsync(string? dateFrom = null, string? dateTo = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/tax-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudSaleReturnReportAsync(string? dateFrom = null, string? dateTo = null, string? customerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/sale-return-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("customer_id", customerId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudPurchaseReturnReportAsync(string? dateFrom = null, string? dateTo = null, string? supplierId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/purchase-return-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("supplier_id", supplierId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudSalaryReportAsync(string? dateFrom = null, string? dateTo = null, string? employeeId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/salary-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("employee_id", employeeId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudDamageReportAsync(string? dateFrom = null, string? dateTo = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/damage-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudSupplierLedgerReportAsync(string supplierId, string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/supplier-ledger-report",
                ("supplier_id", supplierId), ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudSupplierBalanceReportAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/supplier-balance-report");
        }

        public async Task<JsonDocument?> GetCloudLowStockReportAsync(string? categoryId = null, string? brandId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/low-stock-report",
                ("category_id", categoryId), ("brand_id", brandId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudExpireSoonReportAsync(string? dateFrom = null, string? dateTo = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/expire-soon-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudInstallmentReportAsync(string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/installment-report",
                ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudInstallmentDueReportAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/installment-due-report");
        }

        public async Task<JsonDocument?> GetCloudInstallmentCollectionReportAsync(string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/installment-collection-report",
                ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudCustomerLedgerReportAsync(string customerId, string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/customer-ledger-report",
                ("customer_id", customerId), ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudCustomerBalanceReportAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/customer-balance-report");
        }

        public async Task<JsonDocument?> GetCloudCustomerReceiveReportAsync(string? dateFrom = null, string? dateTo = null, string? customerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/customer-receive-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("customer_id", customerId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudServicingReportAsync(string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/servicing-report",
                ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudGstReportAsync(string? dateFrom = null, string? dateTo = null, int format = 1)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/gst-report",
                ("format", format.ToString()), ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudProfitLossReportAsync(string? dateFrom = null, string? dateTo = null, string? outletId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/profit-loss-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("outlet_id", outletId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudAttendanceReportAsync(string? dateFrom = null, string? dateTo = null, string? employeeId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/attendance-report",
                ("date_from", dateFrom), ("date_to", dateTo), ("employee_id", employeeId));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ 6 MISSING REPORT APIs ═══════════

        public async Task<JsonDocument?> GetCloudWarrantyCheckingReportAsync(string? imeiSerial = null, string? customerId = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/warranty-checking-report",
                ("imei_serial", imeiSerial), ("customer_id", customerId));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudDetailedInstallmentDueReportAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/detailed-installment-due-report");
        }

        public async Task<JsonDocument?> GetCloudDetailedAvailableLoyaltyPointReportAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/detailed-available-loyalty-point-report");
        }

        public async Task<JsonDocument?> GetCloudDetailedUsageLoyaltyPointReportAsync(string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/detailed-usage-loyalty-point-report",
                ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        public async Task<JsonDocument?> GetCloudDetailedSchemeReportAsync()
        {
            return await GetDesktopJsonAsync($"{BaseUrl}/api/desktop/detailed-scheme-report");
        }

        public async Task<JsonDocument?> GetCloudDetailedItemTrackingReportAsync(string itemId, string? dateFrom = null, string? dateTo = null)
        {
            var url = BuildQueryUrl($"{BaseUrl}/api/desktop/detailed-item-tracking-report",
                ("item_id", itemId), ("date_from", dateFrom), ("date_to", dateTo));
            return await GetDesktopJsonAsync(url);
        }

        // ═══════════ Internal Helpers ═══════════

        private static string BuildQueryUrl(string baseUrl, params (string key, string value)[] parameters)
        {
            var query = string.Join("&",
                parameters.Where(p => !string.IsNullOrEmpty(p.value))
                          .Select(p => $"{Uri.EscapeDataString(p.key)}={Uri.EscapeDataString(p.value)}"));
            return query.Length > 0 ? $"{baseUrl}?{query}" : baseUrl;
        }

        // ─── Public wrappers (used by BusyImportService) ─────────────

        /// <summary>
        /// GET /api/desktop/{path} → JsonDocument? (public, auth-aware)
        /// </summary>
        public async Task<JsonDocument?> GetDesktopReportJsonAsync(string path)
            => await GetDesktopJsonAsync($"{BaseUrl}{path}");

        /// <summary>
        /// POST /api/desktop/{path} → (ok, responseBody) (public, auth-aware)
        /// </summary>
        public async Task<(bool ok, string body)> PostDesktopReportAsync(string path, object payload)
            => await PostDesktopJsonAsync($"{BaseUrl}{path}", payload);

        private async Task<JsonDocument?> GetDesktopJsonAsync(string url)
        {
            try
            {
                var response = await SendWithRetryAsync(() => Client().GetAsync(url), "DesktopAPI");
                if (!response.IsSuccessStatusCode)
                {
                    if (response.StatusCode == HttpStatusCode.Unauthorized && await EnsureAuthorizedAsync())
                    {
                        response = await SendWithRetryAsync(() => Client().GetAsync(url), "DesktopAPI-retry");
                    }
                    if (!response.IsSuccessStatusCode)
                    {
                        LogService.Warn($"GetDesktopJson failed ({response.StatusCode}): {url}");
                        return null;
                    }
                }
                var body = await response.Content.ReadAsStringAsync();
                return JsonDocument.Parse(body);
            }
            catch (Exception ex)
            {
                LogService.Warn($"GetDesktopJson exception: {ex.Message}");
                return null;
            }
        }

        private async Task<(bool ok, string message)> PostDesktopJsonAsync(string url, object payload)
        {
            try
            {
                var json = JsonSerializer.Serialize(payload, JsonOptions);
                var content = new StringContent(json, Encoding.UTF8, "application/json");
                var response = await SendWithRetryAsync(() => Client().PostAsync(url, content), "DesktopAPI-POST");
                if (!response.IsSuccessStatusCode && response.StatusCode == HttpStatusCode.Unauthorized)
                {
                    if (await EnsureAuthorizedAsync())
                    {
                        content = new StringContent(json, Encoding.UTF8, "application/json");
                        response = await SendWithRetryAsync(() => Client().PostAsync(url, content), "DesktopAPI-POST-retry");
                    }
                }
                var body = await response.Content.ReadAsStringAsync();
                if (!response.IsSuccessStatusCode)
                    LogService.Warn($"PostDesktopJson failed ({response.StatusCode}): {url}");
                return response.IsSuccessStatusCode ? (true, body) : (false, body);
            }
            catch (Exception ex)
            {
                LogService.Warn($"PostDesktopJson exception: {ex.Message}");
                return (false, ex.Message);
            }
        }

        private async Task<(bool ok, string message)> DeleteDesktopAsync(string url)
        {
            try
            {
                var response = await SendWithRetryAsync(() => Client().DeleteAsync(url), "DesktopAPI-DELETE");
                if (!response.IsSuccessStatusCode && response.StatusCode == HttpStatusCode.Unauthorized)
                {
                    if (await EnsureAuthorizedAsync())
                    {
                        response = await SendWithRetryAsync(() => Client().DeleteAsync(url), "DesktopAPI-DELETE-retry");
                    }
                }
                var body = await response.Content.ReadAsStringAsync();
                if (!response.IsSuccessStatusCode)
                    LogService.Warn($"DeleteDesktop failed ({response.StatusCode}): {url}");
                return response.IsSuccessStatusCode ? (true, body) : (false, body);
            }
            catch (Exception ex)
            {
                LogService.Warn($"DeleteDesktop exception: {ex.Message}");
                return (false, ex.Message);
            }
        }

        private async Task<(bool ok, string message)> PutDesktopJsonAsync(string url, object payload)
        {
            try
            {
                var json = JsonSerializer.Serialize(payload, JsonOptions);
                var content = new StringContent(json, Encoding.UTF8, "application/json");
                var request = new HttpRequestMessage(HttpMethod.Put, url) { Content = content };
                var response = await SendWithRetryAsync(() => Client().SendAsync(request), "DesktopAPI-PUT");
                if (!response.IsSuccessStatusCode && response.StatusCode == HttpStatusCode.Unauthorized)
                {
                    if (await EnsureAuthorizedAsync())
                    {
                        content = new StringContent(json, Encoding.UTF8, "application/json");
                        request = new HttpRequestMessage(HttpMethod.Put, url) { Content = content };
                        response = await SendWithRetryAsync(() => Client().SendAsync(request), "DesktopAPI-PUT-retry");
                    }
                }
                var body = await response.Content.ReadAsStringAsync();
                if (!response.IsSuccessStatusCode)
                    LogService.Warn($"PutDesktopJson failed ({response.StatusCode}): {url}");
                return response.IsSuccessStatusCode ? (true, body) : (false, body);
            }
            catch (Exception ex)
            {
                LogService.Warn($"PutDesktopJson exception: {ex.Message}");
                return (false, ex.Message);
            }
        }

        /// <summary>
        /// Extract a string value from a JSON element that may be a string OR a number
        /// (Laravel JSON responses often emit numbers as raw JSON numbers).
        /// </summary>
        private static string JsonStr(JsonElement el)
        {
            try
            {
                if (el.ValueKind == JsonValueKind.String) return el.GetString() ?? "";
                if (el.ValueKind == JsonValueKind.Number) return el.GetRawText();
                return el.ValueKind == JsonValueKind.Null ? "" : el.ToString();
            }
            catch { return ""; }
        }
    }

    public class CloudAccountBalance
    {
        public string AccountName { get; set; } = "";
        public string Balance { get; set; } = "0";
    }

    public class CloudAccountStatement
    {
        public int Sn { get; set; }
        public string Date { get; set; } = "";
        public string Title { get; set; } = "";
        public string Debit { get; set; } = "";
        public string Credit { get; set; } = "";
        public string Balance { get; set; } = "";
        public string AddedDateTime { get; set; } = "";
    }

    public class CloudTransactionHistory
    {
        public string Date { get; set; } = "";
        public string ReferenceNo { get; set; } = "";
        public string Type { get; set; } = "";
        public string PaymentMethod { get; set; } = "";
        public string Amount { get; set; } = "";
        public string CreatedAt { get; set; } = "";
    }

    public class CloudTrialBalance
    {
        public int Sn { get; set; }
        public string Title { get; set; } = "";
        public string Debit { get; set; } = "";
        public string Credit { get; set; } = "";
    }

    public class CloudTrialBalanceResult
    {
        public List<CloudTrialBalance> Rows { get; set; } = new();
        public string TotalDebit { get; set; } = "0";
        public string TotalCredit { get; set; } = "0";
    }

    public class CloudBalanceSheetItem
    {
        public int Sn { get; set; }
        public string Title { get; set; } = "";
        public string Amount { get; set; } = "";
    }

    public class CloudBalanceSheetResult
    {
        public List<CloudBalanceSheetItem> Assets { get; set; } = new();
        public List<CloudBalanceSheetItem> Liabilities { get; set; } = new();
        public string TotalAssets { get; set; } = "0";
        public string TotalLiabilities { get; set; } = "0";
        public string NetWorth { get; set; } = "0";
    }

    public class CloudPermission
    {
        public long Id { get; set; }
        public string Name { get; set; } = "";
        public string GroupName { get; set; } = "";
    }
}
