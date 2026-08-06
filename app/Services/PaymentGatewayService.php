<?php

namespace App\Services;

use Mdiqbal\LaravelPayments\Facades\Payment;
use Mdiqbal\LaravelPayments\DTO\PaymentRequest;
use Mdiqbal\LaravelPayments\DTO\PaymentResponse;
use Mdiqbal\LaravelPayments\Exceptions\PaymentException;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Sale\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Central Payment Gateway Service
 * 
 * This service provides a unified interface for all payment gateways.
 * It handles payment processing, verification, and refunds for multiple gateways.
 */
class PaymentGatewayService
{
    /**
     * Process payment using specified gateway
     * 
     * @param string $gatewayName Gateway name (e.g., 'stripe', 'paypal')
     * @param float $amount Payment amount
     * @param string $currency Currency code (default: USD)
     * @param string|null $description Payment description
     * @param array $metadata Additional metadata
     * @param int|null $paymentMethodId Payment method ID from database
     * @return PaymentResponse
     * @throws PaymentException
     */
    public function processPayment(
        string $gatewayName,
        float $amount,
        string $currency = 'USD',
        ?string $description = null,
        array $metadata = [],
        ?int $paymentMethodId = null
    ): PaymentResponse {
        try {
            // Get payment method configuration if provided
            $gatewayConfig = null;
            $originalConfig = null;

            if ($paymentMethodId) {
                $gatewayConfig = $this->getGatewayConfig($gatewayName, $paymentMethodId);
                
                // Ensure mode is set if it's null or empty (default to sandbox)
                if (empty($gatewayConfig['mode']) || $gatewayConfig['mode'] === null) {
                    $gatewayConfig['mode'] = 'sandbox';
                }
                
                // Log config for debugging
                Log::debug('Razorpay gateway config from payment method', [
                    'payment_method_id' => $paymentMethodId,
                    'gateway' => $gatewayName,
                    'config' => $gatewayConfig,
                    'has_key_id' => isset($gatewayConfig['key_id']) && !empty($gatewayConfig['key_id']),
                    'has_key_secret' => isset($gatewayConfig['key_secret']) && !empty($gatewayConfig['key_secret']),
                    'mode' => $gatewayConfig['mode'] ?? 'not set',
                ]);
                
                // If we have config from database, temporarily set it in config system
                if (!empty($gatewayConfig)) {
                    $originalConfig = config("payments.gateways.{$gatewayName}");
                    $this->setGatewayConfig($gatewayName, $gatewayConfig);
                    
                    // Log formatted config (without sensitive data)
                    $formattedConfig = config("payments.gateways.{$gatewayName}");
                    $mode = $formattedConfig['mode'] ?? 'unknown';
                    $modeConfig = $formattedConfig[$mode] ?? [];
                    
                    Log::debug('Razorpay formatted config after setGatewayConfig', [
                        'gateway' => $gatewayName,
                        'mode' => $mode,
                        'has_mode_config' => !empty($modeConfig),
                        'key_id_set' => isset($modeConfig['key_id']) && !empty($modeConfig['key_id']),
                        'key_secret_set' => isset($modeConfig['key_secret']) && !empty($modeConfig['key_secret']),
                        'key_id_prefix' => isset($modeConfig['key_id']) ? substr($modeConfig['key_id'], 0, 10) . '...' : 'not set',
                    ]);
                } else {
                    Log::warning('Razorpay gateway config is empty from payment method', [
                        'payment_method_id' => $paymentMethodId,
                        'gateway' => $gatewayName,
                    ]);
                }
            } else {
                // Even without paymentMethodId, verify config exists
                $gatewayConfig = $this->getGatewayConfig($gatewayName);
                if (empty($gatewayConfig)) {
                    Log::warning('Gateway config is empty', [
                        'gateway' => $gatewayName,
                        'payment_method_id' => $paymentMethodId,
                    ]);
                }
            }

            // Generate transaction/order ID first
            $transactionId = $metadata['transaction_id'] ?? 'txn_' . uniqid();

            // Get customer information
            $customerEmail = $metadata['customer_email'] ?? $metadata['email'] ?? null;
            $customerName = $metadata['customer_name'] ?? $metadata['name'] ?? null;
            $customerPhone = $metadata['customer_phone'] ?? $metadata['phone'] ?? null;
            $customer = null;
            
            // If customer_id is provided, fetch customer from database to get missing info
            if (isset($metadata['customer_id']) && $metadata['customer_id']) {
                $customerModel = Customer::find($metadata['customer_id']);
                if ($customerModel) {
                    if (!$customerEmail && $customerModel->email) {
                        $customerEmail = $customerModel->email;
                    }
                    if (!$customerName && $customerModel->name) {
                        $customerName = $customerModel->name;
                    }
                    if (!$customerPhone && $customerModel->phone) {
                        $customerPhone = $customerModel->phone;
                    }
                    
                    // Build customer array
                    $customer = array_filter([
                        'name' => $customerName,
                        'email' => $customerEmail,
                        'phone' => $customerPhone,
                    ]);
                }
            }
            
            // Fallback to a default email if still not found
            if (!$customerEmail) {
                $customerEmail = 'customer@example.com';
            }

            // Get return URL from metadata or generate default
            // Gateway-specific callback URLs
            $gatewayCallbackRoutes = [
                'paytm' => 'pos.payment.paytm.callback',
                'paystack' => 'pos.payment.paystack.callback',
                'flutterwave' => 'pos.payment.flutterwave.callback',
                'myfatoorah' => 'pos.payment.myfatoorah.callback',
                'mpesa' => 'pos.payment.mpesa.callback',
            ];
            
            if (isset($gatewayCallbackRoutes[$gatewayName])) {
                // Use return_url from metadata if provided, otherwise generate default
                $returnUrl = $metadata['return_url'] ?? $metadata['callback_url'];
                
                // If not provided, try to generate from route
                if (empty($returnUrl)) {
                    try {
                        $returnUrl = route($gatewayCallbackRoutes[$gatewayName], [], false);
                        // Convert relative URL to absolute
                        $returnUrl = url($returnUrl);
                    } catch (\Exception $e) {
                        // If route doesn't exist, use absolute URL
                        $returnUrl = url('/pos/payment/' . $gatewayName . '/callback');
                    }
                }
                
                // Ensure it's an absolute URL
                if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
                    $returnUrl = url($returnUrl);
                }
                
                // Some gateways require HTTPS in production
                if (in_array($gatewayName, ['paytm', 'paystack', 'flutterwave', 'myfatoorah']) && 
                    config('app.env') === 'production' && strpos($returnUrl, 'http://') === 0) {
                    $returnUrl = str_replace('http://', 'https://', $returnUrl);
                }
                
                \Log::info("{$gatewayName} callback URL generated", [
                    'return_url' => $returnUrl,
                    'from_metadata' => isset($metadata['return_url']) || isset($metadata['callback_url']),
                ]);
            } else {
                $returnUrl = $metadata['return_url'] ?? $metadata['callback_url'] ?? url('/payment/callback');
            }

            // Get cancel URL from metadata (optional)
            $cancelUrl = $metadata['cancel_url'] ?? null;

            // Get webhook/notify URL from metadata (optional)
            $notifyUrl = $metadata['webhook_url'] ?? $metadata['notify_url'] ?? null;

            // Prepare metadata (excluding keys that are used as direct parameters)
            // Note: payment_method_id is excluded from metadata because it's a database ID, not a Stripe PaymentMethod ID
            $requestMetadata = array_merge([
                'gateway' => $gatewayName,
            ], array_filter($metadata, function($key) {
                return !in_array($key, ['transaction_id', 'customer_email', 'email', 'customer_name', 'name', 'customer_phone', 'phone', 'return_url', 'callback_url', 'webhook_url', 'notify_url', 'cancel_url', 'customer_id', 'payment_method_id']);
            }, ARRAY_FILTER_USE_KEY));
            
            // Add database payment_method_id to metadata with a different key to avoid confusion with Stripe PaymentMethod IDs
            if ($paymentMethodId) {
                $requestMetadata['db_payment_method_id'] = $paymentMethodId;
            }

            // Add cancel_url to metadata for PayPal (since PaymentRequest doesn't support it directly)
            if ($cancelUrl) {
                $requestMetadata['cancel_url'] = $cancelUrl;
            }
            
            // Create payment request using the correct constructor format
            // Constructor signature: orderId, amount, currency, customerEmail, callbackUrl, webhookUrl, meta, customerName, customerPhone, description, customData
            $paymentRequest = new PaymentRequest(
                orderId: $transactionId,
                amount: $amount,
                currency: $currency,
                customerEmail: $customerEmail,
                callbackUrl: $returnUrl,
                webhookUrl: $notifyUrl,
                meta: $requestMetadata,
                customerName: $customerName,
                customerPhone: $customerPhone,
                description: $description ?? "Payment for Order",
                customData: []
            );

            // Clear cached gateway instances to ensure fresh config is used
            // This is important when using payment method-specific configurations
            if ($paymentMethodId && !empty($gatewayConfig) && !empty($gatewayName)) {
                $this->clearGatewayCache($gatewayName);
            }

            // Process payment with specified gateway
            try {
                $response = Payment::gateway($gatewayName)->pay($paymentRequest);

                Log::info('Payment processed successfully', [
                    'gateway' => $gatewayName,
                    'transaction_id' => $response->getTransactionId(),
                    'amount' => $amount,
                ]);

                return $response;
            } finally {
                // Restore original config if we modified it
                if ($originalConfig !== null) {
                    Config::set("payments.gateways.{$gatewayName}", $originalConfig);
                }
            }

        } catch (PaymentException $e) {
            Log::error('Payment processing failed', [
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
            ]);
            // Re-throw PaymentException as-is (it already has user-friendly message)
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected payment error', [
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_method_id' => $paymentMethodId,
            ]);
            
            // Provide user-friendly error message
            $errorMessage = $e->getMessage();
            
            // Check for credential/configuration errors
            if (stripos($errorMessage, 'credential') !== false || 
                stripos($errorMessage, 'not configured') !== false ||
                stripos($errorMessage, 'missing') !== false) {
                throw new PaymentException('Payment gateway credentials are not configured correctly. Please check your payment method settings.');
            }
            
            // Check for connection errors
            if (stripos($errorMessage, 'connection') !== false || 
                stripos($errorMessage, 'timeout') !== false ||
                stripos($errorMessage, 'network') !== false) {
                throw new PaymentException('Payment gateway connection error. Please check your internet connection and try again.');
            }
            
            // Generic error
            throw new PaymentException('Payment processing failed. Please try again or contact support.');
        }
    }

    /**
     * Verify payment webhook/callback
     * 
     * @param string $gatewayName Gateway name
     * @param array $payload Webhook payload
     * @return PaymentResponse
     * @throws PaymentException
     */
    public function verifyPayment(string $gatewayName, array $payload): PaymentResponse
    {
        try {
            $response = Payment::gateway($gatewayName)->verify($payload);

            Log::info('Payment verified', [
                'gateway' => $gatewayName,
                'transaction_id' => $response->getTransactionId(),
                'status' => $response->getStatus(),
            ]);

            return $response;

        } catch (PaymentException $e) {
            Log::error('Payment verification failed', [
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process refund
     * 
     * @param string $gatewayName Gateway name
     * @param string $transactionId Transaction ID to refund
     * @param float $amount Refund amount (0 for full refund)
     * @param string|null $reason Refund reason
     * @return bool
     * @throws PaymentException
     */
    public function processRefund(
        string $gatewayName,
        string $transactionId,
        float $amount = 0,
        ?string $reason = null
    ): bool {
        try {
            $success = Payment::gateway($gatewayName)->refund($transactionId, $amount);

            Log::info('Refund processed', [
                'gateway' => $gatewayName,
                'transaction_id' => $transactionId,
                'amount' => $amount,
            ]);

            return $success;

        } catch (PaymentException $e) {
            Log::error('Refund failed', [
                'gateway' => $gatewayName,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve payment status
     * 
     * @param string $gatewayName Gateway name
     * @param string $transactionId Transaction ID
     * @return PaymentResponse
     * @throws PaymentException
     */
    public function retrievePayment(string $gatewayName, string $transactionId): PaymentResponse
    {
        try {
            // Check if gateway supports retrievePayment method
            $gateway = Payment::gateway($gatewayName);
            
            if (method_exists($gateway, 'retrievePayment')) {
                return $gateway->retrievePayment($transactionId);
            }

            throw new PaymentException("Gateway {$gatewayName} does not support payment retrieval");

        } catch (PaymentException $e) {
            Log::error('Payment retrieval failed', [
                'gateway' => $gatewayName,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get gateway configuration
     * 
     * @param string $gatewayName Gateway name
     * @param int|null $paymentMethodId Optional payment method ID to get config from database
     * @return array
     */
    public function getGatewayConfig(string $gatewayName, ?int $paymentMethodId = null): array
    {
        // If payment method ID is provided, get configuration from payment method
        if ($paymentMethodId) {
            $paymentMethod = PaymentMethod::find($paymentMethodId);
            if ($paymentMethod && $paymentMethod->configuration) {
                $config = $paymentMethod->configuration;
                
                // Check if it's new nested structure: config[gateway][...]
                if (isset($config['gateway']) && isset($config[$gatewayName]) && is_array($config[$gatewayName])) {
                    // New nested structure - return the gateway-specific config
                    $gatewayConfig = $config[$gatewayName];
                    // Ensure mode is set (default to sandbox if null or empty)
                    if (empty($gatewayConfig['mode'])) {
                        $gatewayConfig['mode'] = 'sandbox';
                    }
                    return $gatewayConfig;
                }
                
                // Check if it's old flat structure with gateway prefix
                $gatewayPrefix = strtolower($gatewayName) . '_';
                $hasGatewayKeys = false;
                foreach ($config as $key => $value) {
                    if (strpos(strtolower($key), $gatewayPrefix) === 0) {
                        $hasGatewayKeys = true;
                        break;
                    }
                }
                
                if ($hasGatewayKeys) {
                    // Old flat structure - convert to nested for compatibility
                    $nestedConfig = [];
                    foreach ($config as $key => $value) {
                        if (strpos(strtolower($key), $gatewayPrefix) === 0) {
                            $cleanKey = substr($key, strlen($gatewayPrefix));
                            // Normalize mode value for Razorpay: "Sandbox" -> "sandbox", "Live" -> "live"
                            if ($cleanKey === 'mode' && $value && $gatewayName === 'razorpay') {
                                $value = strtolower(trim($value));
                                if ($value === 'test') {
                                    $value = 'sandbox';
                                }
                            }
                            $nestedConfig[$cleanKey] = $value;
                        }
                    }
                    return $nestedConfig;
                }
                
                // If gateway matches, return as-is (might be already in correct format)
                if (isset($config['gateway']) && $config['gateway'] === $gatewayName) {
                    return $config;
                }
            }
        }
        
        // Fallback to config file
        return config("payments.gateways.{$gatewayName}", []);
    }

    /**
     * Check if gateway is available
     * 
     * @param string $gatewayName Gateway name
     * @return bool
     */
    public function isGatewayAvailable(string $gatewayName): bool
    {
        return Payment::hasGateway($gatewayName);
    }

    /**
     * Get available gateways
     * 
     * @return array
     */
    public function getAvailableGateways(): array
    {
        return Payment::getAvailableGateways();
    }

    /**
     * Get payment method gateway name from database
     * 
     * @param int $paymentMethodId Payment method ID
     * @return string|null
     */
    public function getGatewayNameFromPaymentMethod(int $paymentMethodId): ?string
    {
        $paymentMethod = PaymentMethod::find($paymentMethodId);
        
        if (!$paymentMethod || !$paymentMethod->configuration) {
            return null;
        }

        $config = $paymentMethod->configuration;
        
        // New nested structure
        if (isset($config['gateway'])) {
            return $config['gateway'];
        }
        
        // Old flat structure - try to detect gateway from keys
        $gateways = ['stripe', 'paypal', 'razorpay', 'paystack', 'paytm', 'flutterwave', 
                     'sslcommerz', 'mollie', 'senangpay', 'bkash', 'mercadopago', 
                     'cashfree', 'payfast', 'skrill', 'phonepe', 'telr', 'iyzico',
                     'pesapal', 'midtrans', 'myfatoorah', 'easypaisa'];
        
        foreach ($gateways as $gateway) {
            $prefix = $gateway . '_';
            foreach ($config as $key => $value) {
                if (strpos(strtolower($key), $prefix) === 0) {
                    return $gateway;
                }
            }
        }
        
        return null;
    }

    /**
     * Check if payment method uses a gateway
     * 
     * @param int $paymentMethodId Payment method ID
     * @return bool
     */
    public function isGatewayPaymentMethod(int $paymentMethodId): bool
    {
        $gatewayName = $this->getGatewayNameFromPaymentMethod($paymentMethodId);
        return $gatewayName !== null && $this->isGatewayAvailable($gatewayName);
    }

    /**
     * Set gateway configuration in config system
     * Formats the config to match the expected structure
     * 
     * @param string $gatewayName Gateway name
     * @param array $config Configuration array from database
     * @return void
     */
    protected function setGatewayConfig(string $gatewayName, array $config): void
    {
        // Get existing config structure to preserve it
        $existingConfig = config("payments.gateways.{$gatewayName}", []);
        
        // If config already has mode structure (sandbox/live), use it as is
        if (isset($config['mode']) && (isset($config['sandbox']) || isset($config['live']))) {
            Config::set("payments.gateways.{$gatewayName}", $config);
            return;
        }

        // Determine mode from config or use existing mode or default to sandbox
        $rawMode = $config['mode'] ?? $existingConfig['mode'] ?? config('payments.mode', 'sandbox');
        
        // Handle null or empty mode - default to sandbox
        if (empty($rawMode) || $rawMode === null) {
            $rawMode = 'sandbox';
        }
        
        // Normalize mode: handle "Sandbox"/"Live" (capitalized) and "test" -> "sandbox"
        $mode = is_string($rawMode) ? strtolower(trim($rawMode)) : 'sandbox';
        if (in_array($mode, ['test', 'sandbox', 'development'])) {
            $mode = 'sandbox';
        } elseif ($mode === 'production') {
            $mode = 'live';
        }
        // Ensure mode is valid (only for Razorpay, other gateways might have different modes)
        if ($gatewayName === 'razorpay' && !in_array($mode, ['sandbox', 'live'])) {
            $mode = 'sandbox'; // Default to sandbox for Razorpay if invalid
        }
        
        // Build formatted config starting with existing structure
        $formattedConfig = array_merge($existingConfig, [
            'mode' => $mode,
        ]);
        
        // Gateway-specific key mappings (e.g., PayPal uses client_secret but config might have secret_key)
        $gatewayKeyMappings = [
            'paypal' => [
                'secret_key' => 'client_secret', // Map secret_key to client_secret for PayPal
            ],
        ];
        
        // Apply gateway-specific key mappings
        if (isset($gatewayKeyMappings[$gatewayName])) {
            foreach ($gatewayKeyMappings[$gatewayName] as $oldKey => $newKey) {
                if (isset($config[$oldKey]) && !isset($config[$newKey])) {
                    $config[$newKey] = $config[$oldKey];
                    unset($config[$oldKey]);
                }
            }
        }

        // List of keys that should go in the mode section (sandbox/live)
        $modeKeys = ['secret_key', 'api_key', 'key_id', 'key_secret', 'client_id', 'client_secret', 
                     'merchant_id', 'merchant_key', 'public_key', 'access_token', 'app_id', 
                     'app_key', 'app_secret', 'username', 'password', 'store_id', 'store_password',
                     'store_name', 'store_auth_key', 'consumer_key', 'consumer_secret', 'ipn_id',
                     'server_key', 'client_key', 'hash_key', 'merchant_email', 'api_password',
                     'merchant_user_id', 'key_index', 'pass_phrase'];
        
        // Check if config has direct mode keys (not nested in sandbox/live)
        $hasDirectModeKeys = false;
        foreach ($modeKeys as $key) {
            if (isset($config[$key])) {
                $hasDirectModeKeys = true;
                break;
            }
        }

        if ($hasDirectModeKeys) {
            // Config has direct keys - put them in the mode section
            // Ensure mode is valid (should be 'sandbox' or 'live' at this point)
            if (!in_array($mode, ['sandbox', 'live'])) {
                $mode = 'sandbox'; // Force to sandbox if invalid
                $formattedConfig['mode'] = $mode;
            }
            
            if (!isset($formattedConfig[$mode]) || !is_array($formattedConfig[$mode])) {
                $formattedConfig[$mode] = [];
            }
            
            // Also check for capitalized mode keys (Sandbox/Live) and merge them
            $capitalizedMode = ucfirst($mode);
            if (isset($config[$capitalizedMode]) && is_array($config[$capitalizedMode])) {
                $formattedConfig[$mode] = array_merge($formattedConfig[$mode], $config[$capitalizedMode]);
            }
            
            foreach ($modeKeys as $key) {
                if (isset($config[$key]) && !empty($config[$key])) {
                    $formattedConfig[$mode][$key] = $config[$key];
                }
            }
            
            // Copy other keys (like webhook_secret) to top level
            foreach ($config as $key => $value) {
                if (!in_array($key, $modeKeys) && $key !== 'mode' && $key !== 'gateway' && 
                    strtolower($key) !== strtolower($mode) && strtolower($key) !== $capitalizedMode) {
                    $formattedConfig[$key] = $value;
                }
            }
        } else {
            // Config might already be in mode structure or needs merging
            // Merge config into existing structure
            foreach ($config as $key => $value) {
                if ($key !== 'mode' && $key !== 'gateway') {
                    $lowerKey = strtolower($key);
                    // If it's a mode section (sandbox/live or Sandbox/Live), merge it
                    if (in_array($lowerKey, ['sandbox', 'live'])) {
                        // Normalize to lowercase mode key
                        if (!isset($formattedConfig[$mode]) || !is_array($formattedConfig[$mode])) {
                            $formattedConfig[$mode] = [];
                        }
                        if (is_array($value)) {
                            $formattedConfig[$mode] = array_merge($formattedConfig[$mode], $value);
                        } else {
                            $formattedConfig[$mode][$key] = $value;
                        }
                    } else {
                        // Otherwise, set at top level
                        $formattedConfig[$key] = $value;
                    }
                }
            }
        }

        Config::set("payments.gateways.{$gatewayName}", $formattedConfig);
    }

    /**
     * Clear cached gateway instances
     * This ensures fresh instances are created with updated configuration
     * 
     * @param string $gatewayName Gateway name
     * @return void
     */
    protected function clearGatewayCache(string $gatewayName): void
    {
        // Validate gateway name is not empty
        if (empty($gatewayName)) {
            Log::warning('Attempted to clear gateway cache with empty gateway name');
            return;
        }
        
        try {
            // Clear cache in GatewayResolver
            $resolver = app(\Mdiqbal\LaravelPayments\Core\GatewayResolver::class);
            if (method_exists($resolver, 'clearCache')) {
                // For specific gateway, we need to clear just that one
                // Since clearCache() clears all, we'll use reflection to clear specific one
                $reflection = new \ReflectionClass($resolver);
                if ($reflection->hasProperty('instances')) {
                    $property = $reflection->getProperty('instances');
                    $property->setAccessible(true);
                    $instances = $property->getValue($resolver);
                    unset($instances[strtolower($gatewayName)]);
                    $property->setValue($resolver, $instances);
                }
            }

            // Clear cache in PaymentManager
            $paymentManager = app(\Mdiqbal\LaravelPayments\Core\PaymentManager::class);
            $reflection = new \ReflectionClass($paymentManager);
            if ($reflection->hasProperty('gateways')) {
                $property = $reflection->getProperty('gateways');
                $property->setAccessible(true);
                $gateways = $property->getValue($paymentManager);
                unset($gateways[strtolower($gatewayName)]);
                $property->setValue($paymentManager, $gateways);
            }
        } catch (\Exception $e) {
            // If clearing cache fails, log but continue
            Log::warning('Failed to clear gateway cache', [
                'gateway' => $gatewayName,
                'error' => $e->getMessage()
            ]);
        }
    }
}
