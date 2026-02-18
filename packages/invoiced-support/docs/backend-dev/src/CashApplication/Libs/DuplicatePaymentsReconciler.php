<?php

namespace App\CashApplication\Libs;

use App\CashApplication\Models\Payment;

/**
 * This class can be used to find duplicate payments created from remittance advice and to merge a new payment with an
 * existing one that is determined to be a duplicate.
 */
class DuplicatePaymentsReconciler
{
    private const BLOCK_PROPERTIES = [
        'created_at',
        'updated_at',
        'amount',
        'balance',
        'date',
    ];

    public function detectDuplicatePayment(Payment $payment): ?Payment
    {
        $existingPayments = Payment::where('source', Payment::SOURCE_REMITTANCE_ADVICE)
            ->where('amount', $payment->amount)
            ->where('currency', $payment->currency)
            ->where('voided', false)
            ->where('applied', false)
            ->sort('date DESC')
            ->all();

        if (0 == count($existingPayments)) {
            return null;
        } elseif (count($existingPayments) > 1 && $payment->customer) {
            /** @var Payment $existingPayment */
            $existingPayment = Payment::where('source', Payment::SOURCE_REMITTANCE_ADVICE)
                ->where('amount', $payment->amount)
                ->where('currency', $payment->currency)
                ->where('voided', false)
                ->where('applied', false)
                ->where('customer', $payment->customer)
                ->sort('date DESC')
                ->oneOrNull();

            return $existingPayment;
        }

        return $existingPayments[0];
    }

    public function mergeDuplicatePayments(Payment $existingPayment, array $newPayment): Payment
    {
        foreach (self::BLOCK_PROPERTIES as $property) {
            unset($newPayment[$property]);
        }

        foreach ($newPayment as $key => $value) {
            if (null == $value) {
                unset($newPayment[$key]);
            }
        }

        // metadata should be calculated before set setValues
        $existingMetadata = $existingPayment->metadata;
        foreach ($newPayment['metadata'] as $key => $value) {
            if (null !== $value) {
                $existingPayment->metadata->$key = $value;
            }
        }

        $existingPayment->setValues($newPayment);
        $existingPayment->metadata = $existingMetadata;
        $existingPayment->saveOrFail();

        return $existingPayment;
    }
}
