<?php

namespace App\PaymentProcessing\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Models\AchFileFormat;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use App\PaymentProcessing\Models\CustomerPaymentBatchItem;

class CreateCustomerPaymentBatch
{
    public function __construct(
        private TenantContext $tenant,
    ) {
    }

    /**
     * @throws ModelException
     */
    public function create(array $parameters): CustomerPaymentBatch
    {
        if (isset($parameters['ach_file_format'])) {
            $parameters['ach_file_format'] = AchFileFormat::findOrFail($parameters['ach_file_format']);
        }

        if (!isset($parameters['currency'])) {
            $parameters['currency'] = $this->tenant->get()->currency;
        }

        $batchPayment = new CustomerPaymentBatch();
        foreach ($parameters as $k => $v) {
            $batchPayment->$k = $v;
        }

        $batchPayment->saveOrFail();

        // Look up all eligible charges to add to batch
        if (!isset($parameters['charges'])) {
            $parameters['charges'] = CustomerPaymentBatch::getEligibleCharges();
        }

        if (0 == count($parameters['charges'])) {
            throw new ModelException('There are no charges eligible to be added to a new payment batch.');
        }

        $total = Money::zero($batchPayment->currency);
        foreach ($parameters['charges'] as $item) {
            if ($item instanceof Charge) {
                $charge = $item;
            } else {
                $charge = Charge::findOrFail($item['id']);
            }

            // Validate that the charge is pending
            if (Charge::PENDING != $charge->status) {
                throw new ModelException('Charge must be pending');
            }

            // Validate that it does not already belong to a batch
            $exists = CustomerPaymentBatchItem::where('charge_id', $charge)->count();
            if ($exists > 0) {
                throw new ModelException('Charge already belongs to a different batch');
            }

            // Validate that it has a correct bank account attached
            $bankAccount = $charge->payment_source;
            if (!$bankAccount instanceof BankAccount) {
                throw new ModelException('Charge must have a bank account payment source.');
            }

            if ('nacha' != $bankAccount->gateway) {
                throw new ModelException('Charge must have been processed using the `nacha` payment gateway.');
            }

            $amount = Money::fromDecimal($charge->currency, $charge->amount);
            $total = $total->add($amount);

            $batchPaymentItem = new CustomerPaymentBatchItem();
            $batchPaymentItem->customer_payment_batch = $batchPayment;
            $batchPaymentItem->charge = $charge;
            $batchPaymentItem->saveOrFail();
        }

        $batchPayment->total = $total->toDecimal();
        $batchPayment->saveOrFail();

        return $batchPayment;
    }
}
