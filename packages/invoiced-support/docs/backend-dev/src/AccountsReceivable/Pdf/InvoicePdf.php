<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\Invoice;

/**
 * @property Invoice $document
 */
class InvoicePdf extends DocumentPdf
{
    public function __construct(Invoice $invoice)
    {
        parent::__construct($invoice);
    }

    public function generateHtmlParameters(): array
    {
        $variables = parent::generateHtmlParameters();
        $paymentPlan = $this->document->PaymentPlan();
        // decorate date on invoice installments
        if ($paymentPlan) {
            $variables['paymentPlan'] = $paymentPlan->toArray();
            $variables['paymentPlan']['installments'] = array_map(function ($installment) {
                $installment['date'] = !is_int($installment['date']) ?: date($this->document->dateFormat(), $installment['date']);

                return $installment;
            }, $variables['paymentPlan']['installments']);
        } else {
            $variables['paymentPlan'] = null;
        }

        return $variables;
    }
}
