<?php

namespace App\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Interfaces\AccountsPayablePaymentMethodInterface;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\CreateECheck;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\Core\Orm\Exception\ModelException;

/**
 * ECheck payment method for accounts payable.
 */
class ECheckPaymentMethod implements AccountsPayablePaymentMethodInterface
{
    public function __construct(
        private readonly CreateECheck $createECheck,
    ) {
    }

    public function pay(PayVendorPayment $payment, array $options): VendorPayment
    {
        if (!isset($options['bank_account'])) {
            throw new AccountsPayablePaymentException('Missing payment bank account');
        }

        try {
            $bankAccount = $options['bank_account'];
            $checkNumber = $options['check_number'] ?? 1;

            $vendor = $payment->vendor;
            $data = $vendor->getVendorAddress();
            $data['check_number'] = $checkNumber;
            $data['amount'] = $payment->getAmount();
            $paymentBatch = $options['payment_batch'] ?? null;

            $vendorPayment = $this->createECheck->create($payment, $data, $bankAccount, $vendor, $paymentBatch);

            // Increment the next check number on the bank account
            $bankAccount->check_number = $checkNumber + 1;
            $bankAccount->saveOrFail();

            return $vendorPayment;
        } catch (ModelException $e) {
            throw new AccountsPayablePaymentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
