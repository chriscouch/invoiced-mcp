<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\PaymentMethods\PaymentMethodFactory;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Core\Database\TransactionManager;

class PayVendor
{
    public function __construct(
        private readonly PaymentMethodFactory $factory,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    /**
     * @throws AccountsPayablePaymentException
     */
    public function pay(string $paymentMethodId, PayVendorPayment $payment, array $options): VendorPayment
    {
        $paymentMethod = $this->factory->get($paymentMethodId);

        return $this->transactionManager->perform(function () use ($paymentMethod, $payment, $options) {
            return $paymentMethod->pay($payment, $options);
        });
    }
}
