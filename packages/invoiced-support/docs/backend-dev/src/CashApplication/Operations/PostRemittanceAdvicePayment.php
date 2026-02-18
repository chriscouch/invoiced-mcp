<?php

namespace App\CashApplication\Operations;

use App\CashApplication\Enums\RemittanceAdviceStatus;
use App\CashApplication\Libs\CashApplicationMatchmaker;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\RemittanceAdvice;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Exception\ModelException;

class PostRemittanceAdvicePayment
{
    public function __construct(
        private CashApplicationMatchmaker $matchmaker,
    ) {
    }

    /**
     * @throws ModelException
     */
    public function post(RemittanceAdvice $advice): Payment
    {
        // Do not create a negative payment
        if ($advice->total_net_amount_paid < 0) {
            throw new ModelException('Remittance advice payment amount cannot be negative');
        }

        $payment = new Payment();
        $payment->date = $advice->payment_date->getTimestamp();
        $payment->reference = $advice->payment_reference;
        $payment->method = $advice->payment_method;
        $payment->currency = $advice->currency;
        $payment->source = Payment::SOURCE_REMITTANCE_ADVICE;
        if ($customer = $advice->customer) {
            $payment->setCustomer($customer);
        }
        $appliedTo = $this->buildAppliedTo($advice);
        $total = Money::zero($advice->currency);
        foreach ($appliedTo as $split) {
            if ('invoice' == $split['type']) {
                $amount = Money::fromDecimal($advice->currency, $split['amount']);
                $total = $total->add($amount);
            }
        }
        $payment->amount = $total->toDecimal();
        $payment->applied_to = $appliedTo;
        $payment->saveOrFail();

        $advice->payment = $payment;
        $advice->status = RemittanceAdviceStatus::Posted;
        $advice->saveOrFail();

        // If the payment does not have a customer then we need to
        // start a cash match job. This has to be done here because
        // the import tool does not create payment.created events
        // that would start the cash match job.
        if ($this->matchmaker->shouldLookForMatches($payment)) {
            $this->matchmaker->enqueue($payment, false);
        }

        return $payment;
    }

    private function buildAppliedTo(RemittanceAdvice $advice): array
    {
        $result = [];

        foreach ($advice->getLines() as $adviceLine) {
            if (ObjectType::Invoice == $adviceLine->document_type && $adviceLine->net_amount_paid > 0) {
                $result[] = [
                    'type' => 'invoice',
                    'invoice' => $adviceLine->invoice,
                    'amount' => $adviceLine->net_amount_paid,
                ];
            } elseif (ObjectType::CreditNote == $adviceLine->document_type) {
                // TODO: need to figure out invoice to apply to
                $result[] = [
                    'type' => 'credit_note',
                    'credit_note' => $adviceLine->credit_note,
                    'amount' => $adviceLine->net_amount_paid,
                ];
            }

            // Other remittance advice line items are ignored for now
        }

        return $result;
    }
}
