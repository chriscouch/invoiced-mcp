<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;

/**
 * An immutable value object to represent a charge through a payment gateway.
 */
final readonly class ChargeValueObject
{
    const SUCCEEDED = 'succeeded';
    const PENDING = 'pending';
    const FAILED = 'failed';
    const AUTHORIZED = 'authorized';

    public int $timestamp;

    public function __construct(
        public Customer $customer,
        public Money $amount,
        public string $gateway,
        public string $gatewayId,
        public string $method,
        public string $status,
        public ?MerchantAccount $merchantAccount,
        public ?PaymentSource $source,
        public string $description,
        int $timestamp = 0,
        public ?string $failureReason = null,
    ) {
        $this->timestamp = $timestamp ?: time();
    }

    /**
     * Creates a new charge object with the given method.
     */
    public function withMethod(string $method): self
    {
        return new self(
            customer: $this->customer,
            amount: $this->amount,
            gateway: $this->gateway,
            gatewayId: $this->gatewayId,
            method: $method,
            status: $this->status,
            merchantAccount: $this->merchantAccount,
            source: $this->source,
            description: $this->description,
            timestamp: $this->timestamp,
            failureReason: $this->failureReason,
        );
    }
}
