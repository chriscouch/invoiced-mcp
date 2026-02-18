<?php

namespace App\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Interfaces\AccountsPayablePaymentMethodInterface;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\CreateVendorPayment;
use App\AccountsPayable\ValueObjects\PayVendorPayment;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use App\Core\Orm\Exception\ModelException;

/**
 * Print Check payment method for accounts payable.
 */
class PrintCheckPaymentMethod implements AccountsPayablePaymentMethodInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private CreateVendorPayment $createVendorPayment,
    ) {
    }

    public function pay(PayVendorPayment $payment, array $options): VendorPayment
    {
        if (!isset($options['bank_account'])) {
            throw new AccountsPayablePaymentException('Missing payment bank account');
        }

        $bankAccount = $options['bank_account'];
        $checkNumber = $options['check_number'] ?? 1;

        try {
            $parameters = [
                'vendor' => $payment->vendor,
                'date' => CarbonImmutable::now(),
                'amount' => $payment->getAmount()->toDecimal(),
                'currency' => $payment->getAmount()->currency,
                'payment_method' => PaymentMethod::CHECK,
                'reference' => $checkNumber,
                'bank_account' => $bankAccount,
                'vendor_payment_batch' => $options['payment_batch'] ?? null,
            ];

            $appliedTo = [];
            foreach ($payment->getItems() as $item) {
                $appliedTo[] = [
                    'bill' => $item->bill,
                    'amount' => $item->amount->toDecimal(),
                ];
            }

            $vendorPayment = $this->createVendorPayment->create($parameters, $appliedTo);

            // Increment the next check number on the bank account
            $bankAccount->check_number = $checkNumber + 1;
            $bankAccount->saveOrFail();

            return $vendorPayment;
        } catch (ModelException $e) {
            throw new AccountsPayablePaymentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
