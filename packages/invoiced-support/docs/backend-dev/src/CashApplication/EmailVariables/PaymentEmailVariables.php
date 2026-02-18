<?php

namespace App\CashApplication\EmailVariables;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Core\I18n\MoneyFormatter;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Models\EmailTemplate;

/**
 * View model for payment email templates.
 */
class PaymentEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected Payment $payment)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        $companyVariables = $this->payment->tenant()->getEmailVariables();
        $customerVariables = $this->payment->customer()->getEmailVariables(); /* @phpstan-ignore-line */

        // look for an invoice #
        $invoiceNumbers = [];
        foreach ($this->payment->applied_to as $appliedTo) {
            if (isset($appliedTo['invoice']) && $invoice = Invoice::find($appliedTo['invoice'])) {
                $invoiceNumbers[] = $invoice->number;
            }
        }
        $invoiceNumber = join(', ', $invoiceNumbers);

        $paymentSource = null;
        if ($charge = $this->payment->charge) {
            $paymentSource = $charge->payment_source;
        }

        $formatter = MoneyFormatter::get();
        $moneyFormat = $this->payment->moneyFormat();
        $variables = [
            // payment specific variables
            'invoice_number' => $invoiceNumber,
            'payment_date' => date('M j, Y h:i a', $this->payment->date),
            'payment_method' => $this->payment->getMethod()->toString(),
            'payment_amount' => $formatter->format($this->payment->getAmount(), $moneyFormat),
            'payment_source' => $paymentSource ? $paymentSource->toString(true) : null,
        ];

        return array_replace(
            $companyVariables->generate($template),
            $customerVariables->generate($template),
            $variables
        );
    }

    public function getCurrency(): string
    {
        return $this->payment->currency;
    }
}
