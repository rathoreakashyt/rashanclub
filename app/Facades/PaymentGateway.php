<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Payment Gateway Facade
 * 
 * @method static \Mdiqbal\LaravelPayments\DTO\PaymentResponse processPayment(string $gatewayName, float $amount, string $currency = 'USD', ?string $description = null, array $metadata = [], ?int $paymentMethodId = null)
 * @method static \Mdiqbal\LaravelPayments\DTO\PaymentResponse verifyPayment(string $gatewayName, array $payload)
 * @method static bool processRefund(string $gatewayName, string $transactionId, float $amount = 0, ?string $reason = null)
 * @method static \Mdiqbal\LaravelPayments\DTO\PaymentResponse retrievePayment(string $gatewayName, string $transactionId)
     * @method static array getGatewayConfig(string $gatewayName, ?int $paymentMethodId = null)
 * @method static bool isGatewayAvailable(string $gatewayName)
 * @method static array getAvailableGateways()
 * @method static string|null getGatewayNameFromPaymentMethod(int $paymentMethodId)
 * @method static bool isGatewayPaymentMethod(int $paymentMethodId)
 * 
 * @see \App\Services\PaymentGatewayService
 */
class PaymentGateway extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\PaymentGatewayService::class;
    }
}
