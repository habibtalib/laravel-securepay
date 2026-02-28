<?php

namespace HabibTalib\SecurePay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \HabibTalib\SecurePay\PaymentIntent createPayment(array $params)
 * @method static array getBanks(string $gateway = 'fpx', string $type = 'b2c')
 * @method static bool verifyCallback(array $payload, ?string $signature = null)
 * @method static string getAuthToken()
 * @method static array getPaymentStatus(string $intentUuid)
 *
 * @see \HabibTalib\SecurePay\SecurePayClient
 */
class SecurePay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'securepay';
    }
}
