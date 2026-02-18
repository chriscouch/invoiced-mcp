<?php

namespace App\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Interfaces\AccountsPayablePaymentMethodInterface;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\CreateVendorPayment;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

/**
 * Vendor Pay payment method for accounts payable.
 */
class AchPaymentMethod implements AccountsPayablePaymentMethodInterface
{
    public function __construct(
        private readonly CreateVendorPayment $createVendorPayment,
    ) {
    }

    public function pay(PayVendorPayment $payment, array $options): VendorPayment
    {
        if (!isset($options['bank_account'])) {
            throw new AccountsPayablePaymentException('Missing payment bank account');
        }

        // Find the payee's bank account
        $vendor = $payment->vendor;
        $vendorBankAccount = $vendor->bank_account;
        if (!$vendorBankAccount) {
            throw new AccountsPayablePaymentException('The vendor does not have a bank account on file.');
        }

        // Create the vendor payment
        try {
            $parameters = [
                'vendor' => $vendor,
                'date' => CarbonImmutable::now(),
                'amount' => $payment->getAmount()->toDecimal(),
                'currency' => $payment->getAmount()->currency,
                'payment_method' => PaymentMethod::ACH,
                'bank_account' => $options['bank_account'],
                'vendor_payment_batch' => $options['payment_batch'] ?? null,
            ];

            $appliedTo = [];
            foreach ($payment->getItems() as $bill) {
                $appliedTo[] = [
                    'bill' => $bill->bill,
                    'amount' => $bill->amount->toDecimal(),
                ];
            }

            return $this->createVendorPayment->create($parameters, $appliedTo);
        } catch (ModelException $e) {
            throw new AccountsPayablePaymentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
