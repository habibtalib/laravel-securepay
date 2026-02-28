<?php

namespace HabibTalib\SecurePay;

class PaymentIntent
{
    public readonly string $uuid;
    public readonly string $checkoutUrl;
    public readonly string $status;
    public readonly string $orderNumber;
    public readonly int $amount;
    public readonly array $raw;

    public function __construct(array $data)
    {
        $this->raw = $data;
        $this->uuid = $data['intent_uuid'] ?? $data['uuid'] ?? '';
        $this->checkoutUrl = $data['checkout_url'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->orderNumber = $data['order_number'] ?? '';
        $this->amount = (int) ($data['amount'] ?? 0);
    }

    /**
     * Get the checkout URL to redirect the customer to.
     */
    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }

    /**
     * Check if the intent was created successfully.
     */
    public function isSuccessful(): bool
    {
        return !empty($this->checkoutUrl);
    }

    public function toArray(): array
    {
        return $this->raw;
    }
}
