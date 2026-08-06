<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Facades\PaymentGateway;
use Mdiqbal\LaravelPayments\Exceptions\PaymentException;
use Modules\Accounting\Models\PaymentMethod;
use Illuminate\Support\Facades\Log;

/**
 * Central Payment Gateway Controller
 * 
 * Handles all payment gateway operations (Stripe, PayPal, Paytm, etc.)
 */
class PaymentGatewayController extends Controller
{
    /**
     * Initialize payment (create payment intent/session)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function initializePayment(Request $request): JsonResponse
    {

        try {
            $validated = $request->validate([
                'gateway' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'nullable|string|size:3',
                'description' => 'nullable|string',
                'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
                'return_url' => 'nullable|url',
                'cancel_url' => 'nullable|url',
                'metadata' => 'nullable|array',
            ]);

            // Check if gateway is available
            if (!PaymentGateway::isGatewayAvailable($validated['gateway'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Payment gateway '{$validated['gateway']}' is not available"
                ], 400);
            }

            // Get payment method if provided
            $paymentMethodId = $validated['payment_method_id'] ?? null;
            if ($paymentMethodId) {
                $paymentMethod = PaymentMethod::find($paymentMethodId);
                if (!$paymentMethod) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment method not found'
                    ], 404);
                }
            }

            // Prepare metadata
            $metadata = array_merge([
                'sale_id' => $request->input('sale_id'),
                'customer_id' => $request->input('customer_id'),
                'outlet_id' => session('outlet.outlet_id'),
                'company_id' => session('company.company_id'),
            ], $validated['metadata'] ?? []);


            // Process payment
            $response = PaymentGateway::processPayment(
                gatewayName: $validated['gateway'],
                amount: $validated['amount'],
                currency: $validated['currency'] ?? 'USD',
                description: $validated['description'] ?? "Payment",
                metadata: $metadata,
                paymentMethodId: $paymentMethodId
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transaction_id' => $response->getTransactionId(),
                    'client_secret' => $response->getData()['client_secret'] ?? null,
                    'redirect_url' => $response->getRedirectUrl(),
                    'gateway_response' => $response->getData(),
                ]
            ]);

        } catch (PaymentException $e) {
            Log::error('Payment initialization failed - PaymentException', [
                'error' => $e->getMessage(),
                'gateway' => $validated['gateway'] ?? 'unknown',
                'payment_method_id' => $validated['payment_method_id'] ?? null,
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error_type' => 'payment_gateway'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment initialization failed - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'gateway' => $validated['gateway'] ?? 'unknown',
            ]);

            // Provide user-friendly error message
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = 'Failed to initialize payment. Please check your payment gateway configuration.';
            
            // Check for specific error types
            if (stripos($errorMessage, 'credential') !== false || 
                stripos($errorMessage, 'not configured') !== false) {
                $userFriendlyMessage = 'Payment gateway credentials are not configured correctly. Please check your payment method settings.';
            } elseif (stripos($errorMessage, 'connection') !== false || 
                      stripos($errorMessage, 'timeout') !== false) {
                $userFriendlyMessage = 'Payment gateway connection error. Please check your internet connection and try again.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $userFriendlyMessage,
                'error_type' => 'system'
            ], 500);
        }
    }

    /**
     * Verify payment (webhook/callback handler)
     * 
     * @param Request $request
     * @param string $gateway Gateway name
     * @return JsonResponse
     */
    public function verifyPayment(Request $request, string $gateway): JsonResponse
    {
        try {
            $payload = $request->all();

            $response = PaymentGateway::verifyPayment($gateway, $payload);

            return response()->json([
                'status' => $response->isSuccess() ? 'success' : 'failed',
                'transaction_id' => $response->getTransactionId(),
                'payment_status' => $response->getStatus(),
                'is_success' => $response->isSuccess(),
                'data' => $response->getData(),
            ]);

        } catch (PaymentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed'
            ], 500);
        }
    }

    /**
     * Get payment status
     * 
     * @param Request $request
     * @param string $gateway Gateway name
     * @return JsonResponse
     */
    public function getPaymentStatus(Request $request, string $gateway): JsonResponse
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|string',
            ]);

            $response = PaymentGateway::retrievePayment($gateway, $validated['transaction_id']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'transaction_id' => $response->getTransactionId(),
                    'status' => $response->getStatus(),
                    'is_success' => $response->isSuccess(),
                    'gateway_data' => $response->getData(),
                ]
            ]);

        } catch (PaymentException $e) {
            Log::error('Payment status check failed - PaymentException', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error_type' => 'payment_gateway'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment status retrieval failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Provide user-friendly error message
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = 'Failed to retrieve payment status. Please try again.';
            
            if (stripos($errorMessage, 'credential') !== false || 
                stripos($errorMessage, 'not configured') !== false) {
                $userFriendlyMessage = 'Payment gateway credentials are not configured correctly. Please check your payment method settings.';
            } elseif (stripos($errorMessage, 'connection') !== false || 
                      stripos($errorMessage, 'timeout') !== false) {
                $userFriendlyMessage = 'Payment gateway connection error. Please check your internet connection and try again.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $userFriendlyMessage,
                'error_type' => 'system'
            ], 500);
        }
    }

    /**
     * Process refund
     * 
     * @param Request $request
     * @param string $gateway Gateway name
     * @return JsonResponse
     */
    public function processRefund(Request $request, string $gateway): JsonResponse
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|string',
                'amount' => 'nullable|numeric|min:0',
                'reason' => 'nullable|string',
            ]);

            $success = PaymentGateway::processRefund(
                $gateway,
                $validated['transaction_id'],
                $validated['amount'] ?? 0,
                $validated['reason'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Refund processed successfully',
                'refunded' => $success,
            ]);

        } catch (PaymentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process refund'
            ], 500);
        }
    }

    /**
     * Get available gateways
     * 
     * @return JsonResponse
     */
    public function getAvailableGateways(): JsonResponse
    {
        try {
            $gateways = PaymentGateway::getAvailableGateways();

            return response()->json([
                'status' => 'success',
                'data' => $gateways,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get available gateways'
            ], 500);
        }
    }

    /**
     * Get gateway configuration (public keys, etc.)
     * 
     * @param Request $request
     * @param string $gateway Gateway name
     * @return JsonResponse
     */
    public function getGatewayConfig(Request $request, string $gateway): JsonResponse
    {
        try {
            if (!PaymentGateway::isGatewayAvailable($gateway)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Gateway '{$gateway}' is not available"
                ], 404);
            }

            // Get payment method ID from request if provided
            $paymentMethodId = $request->input('payment_method_id');
            
            // Get configuration (from payment method if ID provided, otherwise from config)
            $config = PaymentGateway::getGatewayConfig($gateway, $paymentMethodId);
            
            if (empty($config)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Configuration not found for gateway '{$gateway}'"
                ], 404);
            }
            
            // Only return safe/public configuration
            $publicConfig = [];
            
            // Note: PaymentGatewayService::getGatewayConfig already returns gateway-specific config
            // (not nested under gateway name), so we work with the config directly
            
            // Handle different configuration structures
            // 1. Config with mode structure (sandbox/live) from config file
            if (isset($config['mode']) && (isset($config['sandbox']) || isset($config['live']))) {
                $mode = $config['mode'] ?? 'sandbox';
                $modeConfig = $config[$mode] ?? [];
                
                // Return only public keys (not secret keys)
                foreach ($modeConfig as $key => $value) {
                    $lowerKey = strtolower($key);
                    if (strpos($lowerKey, 'secret') === false && 
                        strpos($lowerKey, 'password') === false &&
                        strpos($lowerKey, 'private') === false) {
                        $publicConfig[$key] = $value;
                    }
                }
                $publicConfig['mode'] = $mode;
            } else {
                // 2. Direct gateway config (from PaymentGatewayService)
                // Extract public keys from the config
                foreach ($config as $key => $value) {
                    $lowerKey = strtolower($key);
                    // Skip secret keys, passwords, and private keys
                    if (strpos($lowerKey, 'secret') === false && 
                        strpos($lowerKey, 'password') === false &&
                        strpos($lowerKey, 'private') === false &&
                        $key !== 'gateway') { // Skip gateway name if present
                        
                        // For Stripe, map api_key to publishable_key for frontend compatibility
                        if ($gateway === 'stripe' && ($key === 'api_key' || $key === 'publishable_key')) {
                            $publicConfig['api_key'] = $value;
                            $publicConfig['publishable_key'] = $value;
                        } else {
                            $publicConfig[$key] = $value;
                        }
                    }
                }
            }
            
            // Log for debugging (remove in production if needed)
            Log::debug('Gateway config extracted', [
                'gateway' => $gateway,
                'payment_method_id' => $paymentMethodId,
                'public_config_keys' => array_keys($publicConfig),
                'has_publishable_key' => isset($publicConfig['publishable_key']),
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'gateway' => $gateway,
                    'mode' => $config['mode'] ?? null,
                    'public_config' => $publicConfig,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get gateway configuration', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get gateway configuration: ' . $e->getMessage()
            ], 500);
        }
    }
}
