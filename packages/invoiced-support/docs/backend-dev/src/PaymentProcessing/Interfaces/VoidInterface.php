<?php

namespace App\PaymentProcessing\Interfaces;

use App\PaymentProcessing\Exceptions\VoidAlreadySettledException;
use App\PaymentProcessing\Exceptions\VoidException;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * This interface is implemented by payment gateways
 * that support voiding or canceling payments that have
 * not been settled yet.
 */
interface VoidInterface
{
    /**
     * Voids or cancels an authorized transaction that has not been settled yet.
     *
     * @throws VoidException               when the void fails
     * @throws VoidAlreadySettledException when the void fails because the payment is settled
     *                                     and the window for a void has passed
     */
    public function void(MerchantAccount $merchantAccount, string $chargeId): void;
}
