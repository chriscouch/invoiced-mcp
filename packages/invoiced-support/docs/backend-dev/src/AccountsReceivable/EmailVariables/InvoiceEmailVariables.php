<?php

namespace App\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\Models\Invoice;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;

/**
 * View model for invoice email templates.
 *
 * @property Invoice $document
 */
class InvoiceEmailVariables extends DocumentEmailVariables
{
    public function __construct(Invoice $invoice)
    {
        parent::__construct($invoice);
    }

    public function generate(EmailTemplate $template): array
    {
        $variables = parent::generate($template);

        // view button
        $buttonText = $template->getOption(EmailTemplateOption::BUTTON_TEXT) ?? 'View';

        // add update payment info button
        if (EmailTemplate::AUTOPAY_FAILED == $template->id) {
            $customer = $this->document->customer();
            $url = $customer->tenant()->url.'/paymentInfo/'.$customer->client_id;
            $variables['update_payment_info_button'] = EmailHtml::button($buttonText, $url);
        } else {
            $variables['view_invoice_button'] = EmailHtml::button($buttonText, $variables['url']);
        }

        // next payment attempt
        $nextAttempt = 'None';
        if ($next = $this->document->next_payment_attempt) {
            $next = max(time(), $next);
            $nextAttempt = date($this->document->dateFormat(), $next);
        }

        $params = [
            'invoice_number' => $this->document->number,
            'invoice_date' => date($this->document->dateFormat(), $this->document->date),
            'payment_terms' => $this->document->payment_terms,
            'purchase_order' => $this->document->purchase_order,
            'due_date' => ($this->document->due_date > 0) ? date($this->document->dateFormat(), $this->document->due_date) : '',
            'balance' => $this->document->currencyFormat($this->document->balance),
            'next_payment_attempt' => $nextAttempt,
            'attempt_count' => $this->document->attempt_count,
            'payment_url' => (!$this->document->paid) ? $this->document->payment_url : false,
        ];

        return array_replace(
            $variables,
            $this->document->getExtraEmailVariables(),
            $params
        );
    }
}
