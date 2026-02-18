<?php

namespace App\Chasing\EmailVariables;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\MoneyFormatter;
use App\Sending\Email\Interfaces\EmailVariablesInterface;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;

class ChasingStatementEmailVariables implements EmailVariablesInterface
{
    public function __construct(private readonly ChasingEvent $chasingEvent)
    {
    }

    public function generate(EmailTemplate $template): array
    {
        $customer = $this->chasingEvent->getCustomer();
        $company = $customer->tenant();

        $invoices = $this->chasingEvent->getInvoices();

        $moneyFormat = $customer->moneyFormat();
        $formatter = MoneyFormatter::get();
        $dateFormat = $company->date_format;

        // view button
        $buttonText = $template->getOption(EmailTemplateOption::BUTTON_TEXT);
        $button = EmailHtml::button($buttonText, $this->chasingEvent->getClientUrl());

        return [
            'account_balance' => $formatter->format($this->chasingEvent->getBalance(), $moneyFormat),
            'past_due_account_balance' => $formatter->format($this->chasingEvent->getPastDueBalance(), $moneyFormat),
            'invoice_numbers' => $this->getInvoiceNumbers($invoices),
            'invoice_dates' => $this->getDates($invoices, $dateFormat),
            'invoice_due_dates' => $this->getDueDates($invoices, $dateFormat),
            'customer_portal_button' => $button,
        ];
    }

    public function getCurrency(): string
    {
        return $this->chasingEvent->getBalance()->currency;
    }

    /**
     * Converts an array of invoices into invoice #s.
     *
     * @param Invoice[] $invoices
     */
    private function getInvoiceNumbers(array $invoices): string
    {
        $numbers = [];
        foreach ($invoices as $invoice) {
            if ($number = $invoice->number) {
                $numbers[] = $number;
            }
        }

        return $this->listToString($numbers);
    }

    /**
     * Converts an array of invoices into due dates.
     *
     * @param Invoice[] $invoices
     */
    private function getDates(array $invoices, string $dateFormat): string
    {
        $dates = [];
        foreach ($invoices as $invoice) {
            if ($date = $invoice->date) {
                $dates[] = date($dateFormat, $date);
            }
        }

        return $this->listToString($dates);
    }

    /**
     * Converts an array of invoices into due dates.
     *
     * @param Invoice[] $invoices
     */
    private function getDueDates(array $invoices, string $dateFormat): string
    {
        $dueDates = [];
        foreach ($invoices as $invoice) {
            if ($dueDate = $invoice->due_date) {
                $dueDates[] = date($dateFormat, $dueDate);
            }
        }

        return $this->listToString($dueDates);
    }

    /**
     * Converts an array of strings into a grammatically
     * correct list, i.e. Item 1, Item 2, and Item 3.
     *
     * @param string[] $values
     */
    private function listToString(array $values): string
    {
        $n = count($values);
        if (0 == $n) {
            return '';
        } elseif (1 == $n) {
            return $values[0];
        } elseif (2 == $n) {
            return $values[0].' and '.$values[1];
        }

        $begin = array_slice($values, 0, -1);
        $end = $values[$n - 1];

        return implode(', ', $begin).', and '.$end;
    }
}
