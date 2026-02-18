<?php

namespace App\Statements\EmailVariables;

use App\Core\I18n\MoneyFormatter;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\Statements\Libs\AbstractStatement;

/**
 * View model for statement email templates.
 */
class StatementEmailVariables implements EmailVariablesInterface
{
    public function __construct(protected AbstractStatement $statement)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        // use the company's timezone for php date/time functions
        $company = $this->statement->getSendCompany();
        $company->useTimezone();
        $dateFormat = $this->statement->dateFormat();
        $customer = $this->statement->getSendCustomer();
        $moneyFormat = $customer->moneyFormat();
        $formatter = MoneyFormatter::get();

        $companyVariables = $company->getEmailVariables();
        $customerVariables = $customer->getEmailVariables();

        // build the URL for viewing the statement online
        $url = $this->statement->getSendClientUrl();

        // view button
        $buttonText = $template->getOption(EmailTemplateOption::BUTTON_TEXT);

        $params = [
            'statement_start_date' => ($this->statement->start) ? date($dateFormat, $this->statement->start) : false,
            'statement_end_date' => $this->statement->end ? date($dateFormat, $this->statement->end) : date($dateFormat),
            'statement_balance' => $formatter->currencyFormat($this->statement->balance, $this->statement->currency, $moneyFormat),
            'statement_credit_balance' => $formatter->currencyFormat($this->statement->creditBalance, $this->statement->currency, $moneyFormat),
            'statement_url' => $url,
            'view_statement_button' => EmailHtml::button($buttonText, $url),
        ];

        return array_replace(
            $companyVariables->generate($template),
            $customerVariables->generate($template),
            $params
        );
    }

    public function getCurrency(): string
    {
        return $this->statement->currency;
    }
}
