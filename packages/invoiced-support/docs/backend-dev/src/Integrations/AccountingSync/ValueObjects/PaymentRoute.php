<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\CashApplication\Models\Payment;

/**
 * Represents a currency, method combination for
 * routing payments to the correct payment account.
 *
 * Used by PaymentAccountMatcher
 */
final readonly class PaymentRoute
{
    public static function fromPayment(Payment $payment): self
    {
        $merchantAccount = '';
        $charge = $payment->charge;
        if ($charge && $paymentSource = $charge->payment_source) {
            $merchantAccount = (string) $paymentSource->merchant_account_id;
        }

        return new self($payment->currency, $payment->method, $merchantAccount);
    }

    public function __construct(
        public string $currency,
        public string $method,
        public string $merchantAccount,
    ) {
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'method' => $this->method,
            'merchant_account' => $this->merchantAccount,
        ];
    }
}
