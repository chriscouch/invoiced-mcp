<?php

namespace App\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\Models\Estimate;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;

/**
 * View model for estimate email templates.
 *
 * @property Estimate $document
 */
class EstimateEmailVariables extends DocumentEmailVariables
{
    public function __construct(Estimate $estimate)
    {
        parent::__construct($estimate);
    }

    public function generate(EmailTemplate $template): array
    {
        $variables = parent::generate($template);

        // view button
        $buttonText = $template->getOption(EmailTemplateOption::BUTTON_TEXT);
        $button = EmailHtml::button($buttonText, $variables['url']);

        return array_replace($variables, [
            'estimate_number' => $this->document->number,
            'estimate_date' => date($this->document->dateFormat(), $this->document->date),
            'view_estimate_button' => $button,
            'payment_terms' => $this->document->payment_terms,
            'purchase_order' => $this->document->purchase_order,
            'expiration_date' => ($this->document->expiration_date > 0) ? date($this->document->dateFormat(), $this->document->expiration_date) : '',
        ]);
    }
}
