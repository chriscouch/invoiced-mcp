<?php

namespace App\AccountsPayable\Interfaces;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\ValueObjects\PayVendorPayment;

interface AccountsPayablePaymentMethodInterface
{
    /**
     * Pays an item within a batch payment using the payment method.
     *
     * @throws AccountsPayablePaymentException
     */
    public function pay(PayVendorPayment $payment, array $options): VendorPayment;
}
