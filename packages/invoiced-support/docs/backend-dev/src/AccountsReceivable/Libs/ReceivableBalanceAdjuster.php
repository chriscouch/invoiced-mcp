<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Exceptions\AdjustBalanceException;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;

/**
 * Tool for building a payment used to set the starting balance on a receivable document.
 */
class ReceivableBalanceAdjuster
{
    /**
     * Builds a payment to adjust a receivable document to a specific balance.
     *
     * @throws AdjustBalanceException
     */
    public static function sync(Invoice|CreditNote $document, Money $desiredBalance): ?Payment
    {
        // If the balance is already correct then there is no payment to create
        $currentBalance = Money::fromDecimal($document->currency, $document->balance);
        if ($currentBalance->equals($desiredBalance)) {
            return null;
        }

        // The desired balance cannot exceed the total or be negative
        $total = Money::fromDecimal($document->currency, $document->total);
        if ($desiredBalance->greaterThan($total)) {
            throw new AdjustBalanceException('The desired balance ('.$desiredBalance.') cannot be greater than the total ('.$total.')');
        }

        if ($desiredBalance->isNegative()) {
            throw new AdjustBalanceException('The desired balance ('.$desiredBalance.') cannot be negative');
        }

        // If there is any transaction applied to the document that is not
        // an adjustment then we do not proceed with adjusting the balance.
        // In this case we do not know if the desired balance or the Invoiced
        // balance is the source of truth. For example, a payment could be
        // created on Invoiced but not synced to the accounting system yet
        // which would create a mismatched balance.
        $transactionFk = $document instanceof CreditNote ? 'credit_note_id' : 'invoice';
        $count = Transaction::where($transactionFk, $document)
            ->where('type', Transaction::TYPE_DOCUMENT_ADJUSTMENT, '<>')
            ->count();
        if ($count) {
            return null;
        }

        // Check for an existing adjustment payment and modify that if there is one
        $payment = Transaction::where($transactionFk, $document)
            ->where('type', Transaction::TYPE_DOCUMENT_ADJUSTMENT)
            ->oneOrNull()
            ?->payment;

        if (!$payment) {
            $payment = new Payment();
            $payment->currency = $document->currency;
            $payment->amount = 0;
            // 1 second is added to the payment date because it should have a time
            // greater than the document date
            $payment->date = $document->date + 1;
            $payment->setCustomer($document->customer());
        }
        $payment->applied_to = [
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => $total->subtract($desiredBalance)->toDecimal(),
                'document_type' => $document->object,
                $document->object => $document,
            ],
        ];

        if (!$payment->save()) {
            throw new AdjustBalanceException('Could not set balance: '.$payment->getErrors());
        }

        return $payment;
    }
}
