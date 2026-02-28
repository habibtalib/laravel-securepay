<?php

namespace HabibTalib\SecurePay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use HabibTalib\SecurePay\Exceptions\AuthenticationException;
use HabibTalib\SecurePay\Exceptions\PaymentException;
use HabibTalib\SecurePay\Exceptions\ApiException;

class SecurePayClient
{
    protected array $config;
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct(array $config)
    {
        $this->config = $config;
        $env = $config['environment'] ?? 'sandbox';
        $this->baseUrl = $config['urls'][$env] ?? $config['urls']['sandbox'];
        $this->clientId = $config['credentials'][$env]['client_id'] ?? '';
        $this->clientSecret = $config['credentials'][$env]['client_secret'] ?? '';
    }

    // ─── Authentication ───────────────────────────────────────────────

    /**
     * Get JWT auth token, cached automatically.
     *
     * @throws AuthenticationException
     */
    public function getAuthToken(): string
    {
        $cacheStore = $this->config['cache']['store'] ?? null;
        $cacheKey = ($this->config['cache']['prefix'] ?? 'securepay_') . 'auth_token';
        $buffer = $this->config['cache']['ttl_buffer'] ?? 60;

        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();

        $cached = $cache->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new AuthenticationException('SecurePay client_id or client_secret not configured.');
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->timeout(15)
            ->post($this->baseUrl . '/v1/auth');

        if (!$response->successful()) {
            throw new AuthenticationException(
                'SecurePay authentication failed: HTTP ' . $response->status() . ' - ' . $response->body()
            );
        }

        $data = $response->json();

        if (empty($data['auth_token'])) {
            throw new AuthenticationException('SecurePay auth response missing auth_token.');
        }

        $token = $data['auth_token'];

        // Calculate TTL from expired_at
        $ttl = 3600;
        if (!empty($data['expired_at'])) {
            $expires = strtotime($data['expired_at']);
            if ($expires) {
                $ttl = max(60, $expires - time() - $buffer);
            }
        }

        $cache->put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Clear the cached auth token.
     */
    public function clearAuthToken(): void
    {
        $cacheStore = $this->config['cache']['store'] ?? null;
        $cacheKey = ($this->config['cache']['prefix'] ?? 'securepay_') . 'auth_token';
        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
        $cache->forget($cacheKey);
    }

    // ─── Payments ─────────────────────────────────────────────────────

    /**
     * Create a payment intent.
     *
     * @param array $params {
     *   order_number: string,
     *   buyer_name: string,
     *   buyer_email: string,
     *   buyer_phone: string,
     *   amount: int (in cents, e.g. 1500 = RM15.00),
     *   description: string,
     *   callback_url?: string (defaults to config),
     *   redirect_url?: string (defaults to config),
     * }
     * @return PaymentIntent
     * @throws PaymentException
     */
    public function createPayment(array $params): PaymentIntent
    {
        $required = ['order_number', 'buyer_name', 'buyer_email', 'buyer_phone', 'amount'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new PaymentException("Missing required field: {$field}");
            }
        }

        $payload = [
            'order_number' => $params['order_number'],
            'buyer_name' => $params['buyer_name'],
            'buyer_email' => $params['buyer_email'],
            'buyer_phone' => $params['buyer_phone'],
            'amount' => (int) $params['amount'],
            'description' => $params['description'] ?? "Payment for {$params['order_number']}",
            'callback_url' => $params['callback_url'] ?? $this->config['callback_url'] ?? '',
            'redirect_url' => $params['redirect_url'] ?? $this->config['redirect_url'] ?? '',
        ];

        $response = $this->authenticatedRequest('POST', '/v1/payment/intents', $payload);

        return new PaymentIntent($response);
    }

    /**
     * Get payment status by intent UUID.
     *
     * @throws ApiException
     */
    public function getPaymentStatus(string $intentUuid): array
    {
        return $this->authenticatedRequest('GET', "/v1/payment/intents/{$intentUuid}");
    }

    // ─── Banks ────────────────────────────────────────────────────────

    /**
     * Get bank list for a gateway.
     *
     * @param string $gateway  fpx|direct_debit|duitnow
     * @param string $type     b2c|b2b1|retail|corporate
     * @return array
     * @throws ApiException
     */
    public function getBanks(string $gateway = 'fpx', string $type = 'b2c'): array
    {
        $data = $this->authenticatedRequest('GET', "/v1/paynet/{$gateway}/banks/{$type}");

        return $data['banks']['retail'] ?? $data['banks'] ?? $data;
    }

    // ─── Callback Verification ────────────────────────────────────────

    /**
     * Verify a callback payload using HMAC-SHA256.
     *
     * @param array $payload    The full callback request data
     * @param string|null $signature  The checksum from the callback (auto-extracted if null)
     * @return bool
     */
    public function verifyCallback(array $payload, ?string $signature = null): bool
    {
        $checksum = $signature ?? $payload['checksum'] ?? $payload['signature'] ?? null;
        if (!$checksum) {
            return false;
        }

        // Remove checksum from payload before computing
        $data = $payload;
        unset($data['checksum'], $data['signature']);

        $computed = hash_hmac('sha256', json_encode($data), $this->clientSecret);

        return hash_equals($computed, $checksum);
    }

    /**
     * Parse callback payment data.
     *
     * @param array $payload
     * @return array{status: string, reference_number: string, intent_uuid: string, order_number: string}
     */
    public function parseCallback(array $payload): array
    {
        $payment = $payload['payment'] ?? $payload;

        return [
            'status' => $payment['status'] ?? 'unknown',
            'reference_number' => $payment['reference_number'] ?? '',
            'intent_uuid' => $payment['intent_uuid'] ?? '',
            'order_number' => $payment['order_number'] ?? $payload['order_number'] ?? '',
        ];
    }

    // ─── HTTP ─────────────────────────────────────────────────────────

    /**
     * Make an authenticated API request.
     *
     * @throws ApiException
     */
    protected function authenticatedRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAuthToken();

        $request = Http::withToken($token)
            ->acceptJson()
            ->timeout(30);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($this->baseUrl . $endpoint, $data),
            'POST' => $request->post($this->baseUrl . $endpoint, $data),
            'PUT' => $request->put($this->baseUrl . $endpoint, $data),
            'DELETE' => $request->delete($this->baseUrl . $endpoint, $data),
            default => throw new ApiException("Unsupported HTTP method: {$method}"),
        };

        if ($response->status() === 401) {
            // Token expired, clear cache and retry once
            $this->clearAuthToken();
            $token = $this->getAuthToken();

            $request = Http::withToken($token)->acceptJson()->timeout(30);
            $response = match (strtoupper($method)) {
                'GET' => $request->get($this->baseUrl . $endpoint, $data),
                'POST' => $request->post($this->baseUrl . $endpoint, $data),
                'PUT' => $request->put($this->baseUrl . $endpoint, $data),
                'DELETE' => $request->delete($this->baseUrl . $endpoint, $data),
            };
        }

        if (!$response->successful()) {
            throw new ApiException(
                "SecurePay API error: HTTP {$response->status()} on {$method} {$endpoint} - {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Get the current base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the current environment.
     */
    public function getEnvironment(): string
    {
        return $this->config['environment'] ?? 'sandbox';
    }
}
