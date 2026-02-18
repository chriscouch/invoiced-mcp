<?php

namespace App\PaymentProcessing\ValueObjects;

use App\Core\I18n\ValueObjects\Money;

/**
 * Represents a refund.
 */
final readonly class RefundValueObject
{
    const string SUCCEEDED = 'succeeded';
    const string PENDING = 'pending';
    const string FAILED = 'failed';
    const string VOIDED = 'voided';

    public int $timestamp;

    public function __construct(
        public Money $amount,
        public string $gateway,
        public ?string $gatewayId,
        public string $status,
        int $timestamp = 0,
        public ?string $message = null,
    ) {
        $this->timestamp = $timestamp ?: time();
    }
}
