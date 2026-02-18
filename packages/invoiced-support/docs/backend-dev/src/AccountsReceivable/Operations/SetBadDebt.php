<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Item;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Orm\Exception\ModelException;

class SetBadDebt
{
    /**
     * Writes off an invoice as bd debt.
     *
     * This should only be called within a database transaction.
     *
     * @throws ModelException
     */
    public function set(Invoice $invoice): Invoice
    {
        if (InvoiceStatus::BadDebt->value === $invoice->status) {
            throw new ModelException('The invoice has already been written off');
        }
        if ($invoice->draft) {
            throw new ModelException("You can't write off draft invoices");
        }
        if ($invoice->voided) {
            throw new ModelException("You can't write off voided invoices");
        }
        if ($invoice->balance <= 0) {
            throw new ModelException("You can't write off invoices with zero balance");
        }

        $item = Item::getCurrent(Item::BAD_DEBT);
        if (null === $item) {
            $item = new Item();
            $item->id = Item::BAD_DEBT;
            $item->type = Item::BAD_DEBT_TYPE;
            $item->name = 'Bad Debt';
            $item->saveOrFail();
        }

        $balance = $invoice->balance;

        $creditNote = new CreditNote();
        $creditNote->customer = $invoice->customer;
        $creditNote->currency = $invoice->currency;
        $creditNote->name = 'Bad Debt';
        $lineItem = $item->lineItem();
        $lineItem['unit_cost'] = $balance;
        $lineItem['quantity'] = 1;
        $creditNote->items = [$lineItem];
        $creditNote->calculate_taxes = false;

        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->currency = $invoice->currency;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'amount' => $balance,
                'invoice' => $invoice,
                'document_type' => 'invoice',
                'credit_note' => $creditNote,
            ],
        ];

        $invoice->mute();
        $payment->saveOrFail();
        $invoice->unmute();

        $invoice->status = InvoiceStatus::BadDebt->value;
        $invoice->paid = false;
        $invoice->closed = true;
        $invoice->date_bad_debt = time();
        $invoice->amount_written_off = $balance;
        $invoice->skipClosedCheck();
        $invoice->saveOrFail();

        return $invoice;
    }
}
