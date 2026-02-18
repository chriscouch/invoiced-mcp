<?php

namespace App\Statements\Pdf;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\MoneyFormatter;
use App\Statements\Libs\AbstractStatement;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;

/**
 * View model for using statements in PDF templates.
 */
class StatementPdfVariables implements PdfVariablesInterface
{
    public function __construct(protected AbstractStatement $statement)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        // use the company's timezone for php date/time functions
        $company = $this->statement->getThemeCompany();
        $company->useTimezone();
        $dateFormat = $this->statement->dateFormat();

        $variables = $this->statement->getValues();
        $htmlify = $opts['htmlify'] ?? true;

        $variables['type'] = $this->statement->type;
        $variables['pastDueOnly'] = $this->statement->pastDueOnly;

        // optional properties
        $optionalKeys = ['previousBalance', 'totalUnapplied', 'previousCreditBalance'];
        foreach ($optionalKeys as $key) {
            if (!$variables[$key]) {
                $variables[$key] = false;
            }
        }

        $variables['hasCredits'] = count($variables['creditDetail']) > 0 || $variables['creditBalance'];

        // format activity
        foreach ($variables['accountDetail'] as &$activity) {
            if ($activity['customer'] instanceof Customer) {
                $activity['customer'] = $activity['customer']->toArray();
            }
            $activity['date'] = date($dateFormat, $activity['date']);

            if (isset($activity['dueDate'])) {
                $activity['dueDate'] = date($dateFormat, $activity['dueDate']);
            }

            // expand the document models
            if (isset($activity['invoice'])) {
                $themeVariables = $activity['invoice']->getThemeVariables();
                $activity['invoice'] = $themeVariables->generate($theme, $opts);
            }

            if (isset($activity['creditNote'])) {
                $themeVariables = $activity['creditNote']->getThemeVariables();
                $activity['creditNote'] = $themeVariables->generate($theme, $opts);
            }

            if ($htmlify) {
                $moneyKeys = ['invoiced', 'paid', 'total', 'balance'];
                $activity = $this->currencyFormat($moneyKeys, $activity);
            }
        }

        foreach ($variables['creditDetail'] as &$activity) {
            $activity['date'] = date($dateFormat, $activity['date']);

            if ($htmlify) {
                $moneyKeys = ['issued', 'charged', 'creditBalance'];
                $activity = $this->currencyFormat($moneyKeys, $activity);
            }
        }

        foreach ($variables['unifiedDetail'] as &$activity) {
            $activity['date'] = date($dateFormat, $activity['date']);

            if (isset($activity['dueDate'])) {
                $activity['dueDate'] = date($dateFormat, $activity['dueDate']);
            }

            // expand the document models
            if (isset($activity['invoice'])) {
                $themeVariables = $activity['invoice']->getThemeVariables();
                $activity['invoice'] = $themeVariables->generate($theme, $opts);
            }

            if (isset($activity['creditNote'])) {
                $themeVariables = $activity['creditNote']->getThemeVariables();
                $activity['creditNote'] = $themeVariables->generate($theme, $opts);
            }

            if ($htmlify) {
                $moneyKeys = ['amount', 'balance'];
                $activity = $this->currencyFormat($moneyKeys, $activity);
            }
        }

        if ($htmlify) {
            // format totals
            $moneyKeys = ['previousBalance', 'totalInvoiced', 'totalPaid', 'totalUnapplied', 'balance', 'previousCreditBalance', 'totalCreditsIssued', 'totalCreditsSpent', 'creditBalance'];
            $variables = $this->currencyFormat($moneyKeys, $variables);

            // aging
            foreach ($variables['aging'] as &$entry) {
                $entry = $this->currencyFormat(['amount'], $entry);
            }
        }

        $variables['start'] = ($this->statement->start) ? date($dateFormat, $this->statement->start) : false;
        $variables['end'] = $this->statement->end ? date($dateFormat, $this->statement->end) : date($dateFormat);

        // sub-customers
        $variables['subCustomers'] = $this->getSubCustomers($this->statement->customer, $variables['customerIds'], $theme, $opts);
        unset($variables['customerIds']);

        return $variables;
    }

    private function currencyFormat(array $keys, array $row): array
    {
        $moneyFormat = $this->statement->customer->moneyFormat();
        $formatter = MoneyFormatter::get();
        $currency = $this->statement->getCurrency();

        foreach ($keys as $key) {
            if (isset($row[$key]) && false !== $row[$key]) {
                $row[$key] = $formatter->currencyFormatHtml($row[$key], $currency, $moneyFormat);
            }
        }

        return $row;
    }

    private function getSubCustomers(Customer $parentCustomer, array $ids, Theme $theme, array $opts): array
    {
        // Remove the parent customer from the list of IDs
        $key = array_search($parentCustomer->id, $ids);
        if (false !== $key) {
            unset($ids[$key]);
        }

        if (0 == count($ids)) {
            return [];
        }

        /** @var Customer[] $customers */
        $customers = Customer::where('id IN ('.implode(',', $ids).')')->all();

        $result = [];
        foreach ($customers as $customer) {
            $result[$customer->id] = $customer->getThemeVariables()->generate($theme, $opts);
        }

        return $result;
    }
}
