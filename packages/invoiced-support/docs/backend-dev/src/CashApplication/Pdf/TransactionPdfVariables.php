<?php

namespace App\CashApplication\Pdf;

use App\PaymentProcessing\Models\PaymentMethod;
use App\CashApplication\Models\Transaction;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;

/**
 * View model for using transaction models in PDF templates.
 */
class TransactionPdfVariables implements PdfVariablesInterface
{
    public function __construct(protected Transaction $transaction)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $variables = $this->transaction->toArray();
        $htmlify = $opts['htmlify'] ?? true;

        // payment date
        $dateFormat = $this->transaction->dateFormat();
        $variables['date'] = date($dateFormat, $variables['date']);
        $variables['time'] = $variables['date'];

        // payment method
        $variables['method'] = $this->transaction->getMethod()->toString();

        // check #
        $variables['check_no'] = (PaymentMethod::CHECK == $this->transaction->method) ? $this->transaction->gateway_id : false;

        // get the payment breakdown
        $breakdown = $this->transaction->breakdown();

        // build invoices
        foreach ($breakdown['invoices'] as &$invoice) {
            $invoiceVariables = $invoice->getThemeVariables();
            $invoice = $invoiceVariables->generate($theme, $opts);
        }
        $variables['invoices'] = $breakdown['invoices'];

        // build credit notes
        foreach ($breakdown['creditNotes'] as &$creditNote) {
            $creditNoteVariables = $creditNote->getThemeVariables();
            $creditNote = $creditNoteVariables->generate($theme, $opts);
        }
        $variables['credit_notes'] = $breakdown['creditNotes'];

        // payment source
        $source = $this->transaction->payment_source;
        $variables['payment_source'] = $source ? $source->toString(true) : false;

        // get the total payment amounts
        $amount = $this->transaction->paymentAmount();
        if ($htmlify) {
            $variables['amount_refunded'] = ($breakdown['refunded']->isPositive()) ? $this->transaction->formatMoneyHtml($breakdown['refunded']) : false;
            $variables['amount_credited'] = ($breakdown['credited']->isPositive()) ? $this->transaction->formatMoneyHtml($breakdown['credited']) : false;
            $variables['amount'] = $this->transaction->formatMoneyHtml($amount);
        } else {
            $variables['amount_refunded'] = ($breakdown['refunded']->isPositive()) ? $breakdown['refunded']->toDecimal() : false;
            $variables['amount_credited'] = ($breakdown['credited']->isPositive()) ? $breakdown['credited']->toDecimal() : false;
            $variables['amount'] = $amount->toDecimal();
        }

        // metadata
        $variables['metadata'] = (array) $variables['metadata'];

        return $variables;
    }
}
