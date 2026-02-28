<?php

namespace HabibTalib\SecurePay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessful
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly array $payment,
        public readonly array $rawPayload,
    ) {}
}
