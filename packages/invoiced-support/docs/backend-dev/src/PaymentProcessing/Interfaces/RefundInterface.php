<?php

namespace App\PaymentProcessing\Interfaces;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\ValueObjects\RefundValueObject;

/**
 * This interface is implemented by payment gateways
 * that support refunding previous payments.
 */
interface RefundInterface
{
    /**
     * Refunds a charge performed on this payment gateway.
     *
     * @param string $chargeId original transaction ID on gateway
     * @param Money  $amount   amount to refund
     *
     * @throws RefundException when the refund attempt fails
     */
    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject;
}
