<?php

namespace App\AccountsReceivable\Pdf;

use App\Core\I18n\TranslatorFacade;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Themes\Models\Theme;

/**
 * View model for invoice PDF templates.
 *
 * @property Invoice $document
 */
class InvoicePdfVariables extends DocumentPdfVariables
{
    public function __construct(Invoice $invoice)
    {
        parent::__construct($invoice);
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $variables = parent::generate($theme, $opts);

        // due date
        $dateFormat = $this->document->dateFormat();
        $variables['due_date'] = ($variables['due_date'] > 0) ? date($dateFormat, $variables['due_date']) : null;

        $variables['amount_paid'] = $this->document->amount_paid + $this->document->amount_credited;
        $variables['payments'] = $this->getPayments();

        $variables['terms'] = $theme->terms;

        // this is kept around for BC with mustache templates
        $htmlify = $opts['htmlify'] ?? true;
        if ($htmlify) {
            // format totals
            $variables['amount_paid'] = $variables['amount_paid'] ? $this->document->currencyFormatHtml($variables['amount_paid']) : $variables['amount_paid'];
            $variables['balance'] = $this->document->currencyFormatHtml($variables['balance']);

            // payment URL
            $variables['payment_url'] = htmlspecialchars($variables['payment_url']);

            // footer
            $variables['terms'] = nl2br(htmlentities((string) $variables['terms'], ENT_QUOTES));

            $variables['customFields'] = $variables['custom_fields'];
        }

        return $variables;
    }

    /**
     * Builds a list of payment activity for the invoice. This
     * can include payments, refunds, and credit notes.
     */
    private function getPayments(): array
    {
        $result = $this->getCreditNotes();

        foreach ($this->getTransactions() as $transaction) {
            if ($paymentSource = $transaction->payment_source) {
                $name = $paymentSource->toString(true);
            } elseif (Transaction::TYPE_REFUND == $transaction->type) {
                $name = TranslatorFacade::get()->trans('labels.refund', [], 'pdf');
                $name .= ' - '.date($this->document->dateFormat(), $transaction->date);
            } else {
                $name = TranslatorFacade::get()->trans('labels.payment', [], 'pdf');
                $name .= ' - '.date($this->document->dateFormat(), $transaction->date);
            }
            $result[] = [
                'name' => $name,
                'amount' => Transaction::TYPE_REFUND == $transaction->type ? $transaction->amount : -$transaction->amount,
            ];
        }

        return $result;
    }

    /**
     * Gets the 10 most recent credit notes applied to this invoice.
     *
     * These recent transactions are intentionally returned in ascending order
     * in order to display them in chronological order on the invoice. This is
     * done to handle cases where there are more than 10 payments.
     */
    private function getCreditNotes(): array
    {
        $transactions = Transaction::where('invoice', $this->document->id())
            ->where('credit_note_id IS NOT NULL')
            ->where('status', Transaction::STATUS_SUCCEEDED)
            ->sort('date DESC,id DESC')
            ->first(10);

        $result = [];
        foreach ($transactions as $transaction) {
            $creditNote = CreditNote::findOrFail($transaction->credit_note_id);
            $result[] = [
                'name' => $creditNote->number,
                'amount' => $transaction->amount,
            ];
        }

        return array_reverse($result);
    }

    /**
     * Gets the 10 most recent payments and refunds applied to this invoice.
     *
     * These recent transactions are intentionally returned in ascending order
     * in order to display them in chronological order on the invoice. This is
     * done to handle cases where there are more than 10 payments.
     */
    private function getTransactions(): array
    {
        $payments = Transaction::where('invoice', $this->document->id())
            ->where('status', Transaction::STATUS_SUCCEEDED)
            ->sort('date DESC,id DESC')
            ->first(10);

        return array_reverse($payments);
    }
}
