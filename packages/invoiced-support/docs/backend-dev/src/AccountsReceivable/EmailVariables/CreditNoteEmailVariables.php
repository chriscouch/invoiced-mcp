<?php

namespace App\AccountsReceivable\EmailVariables;

use App\AccountsReceivable\Models\CreditNote;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;

/**
 * View model for credit note email templates.
 */
class CreditNoteEmailVariables extends DocumentEmailVariables
{
    public function __construct(CreditNote $creditNote)
    {
        parent::__construct($creditNote);
    }

    public function generate(EmailTemplate $template): array
    {
        $variables = parent::generate($template);

        // view button
        $buttonText = $template->getOption(EmailTemplateOption::BUTTON_TEXT);
        $button = EmailHtml::button($buttonText, $variables['url']);

        return array_replace($variables, [
            'credit_note_number' => $this->document->number,
            'credit_note_date' => date($this->document->dateFormat(), $this->document->date),
            'view_credit_note_button' => $button,
        ]);
    }
}
