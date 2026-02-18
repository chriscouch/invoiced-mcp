<?php

namespace App\Reports\PresetReports;

use App\Core\Ledger\Ledger;
use App\Integrations\WePay\Enums\PayoutAccounts;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Reports\Enums\ColumnType;
use App\Reports\ValueObjects\FinancialReportColumn;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Reports\ValueObjects\Section;
use Carbon\CarbonImmutable;
use Money\Money;

class InvoicedPaymentsSummary extends AbstractInvoicedPaymentsReport
{
    public static function getId(): string
    {
        return 'invoiced_payments_summary';
    }

    private const ACCOUNT_NAMES = [
        ['Processed Payments', 'Payments'],
        ['Refunded Payments', 'Refunds'],
        ['Disputed Payments', 'Disputes'],
        ['Processing Fees', 'Processing Fees'],
        ['Bank Account', 'Payouts'],
        [Ledger::ROUNDING_ACCOUNT, Ledger::ROUNDING_ACCOUNT],
    ];

    private Money $startingBalance;
    private Money $closingBalance;

    protected function getName(): string
    {
        return 'Invoiced Payments Summary';
    }

    protected function build(): void
    {
        $wePayData = $this->getWePayData();
        if (!$wePayData) {
            $this->noData();

            return;
        }

        $merchantAccount = $this->getMerchantAccount();
        if (!$merchantAccount instanceof MerchantAccount) {
            $this->noData();

            return;
        }

        $ledger = $this->getLedger($merchantAccount);
        if (!$ledger) {
            $this->noData();

            return;
        }

        $this->addOverviewGroup($wePayData);

        $start = CarbonImmutable::createFromTimestamp($this->start)->subDay();
        $startingAccounts = $ledger->reporting->getAccountBalances($start);
        $diff = [];
        foreach ($startingAccounts as $row) {
            if ($row['name'] == PayoutAccounts::MerchantAccount->value) {
                $this->startingBalance = $row['balance'];
            } else {
                $diff[$row['name']] = $row['balance'];
            }
        }

        $end = CarbonImmutable::createFromTimestamp($this->end);
        $endingAccounts = $ledger->reporting->getAccountBalances($end);
        foreach ($endingAccounts as $row) {
            if ($row['name'] == PayoutAccounts::MerchantAccount->value) {
                $this->closingBalance = $row['balance'];
            } else {
                /** @var Money $previous */
                $previous = $diff[$row['name']];
                $diff[$row['name']] = $row['balance']->subtract($previous);
            }
        }

        $financialReport = new FinancialReportGroup([
            new FinancialReportColumn('', ColumnType::String),
            new FinancialReportColumn('Total', ColumnType::Money),
        ]);

        $mainRow = new FinancialReportRow();

        // Transactions
        $transactions = new FinancialReportRow();
        $transactions->setHeader('Transactions', '');
        $total = null;
        foreach (self::ACCOUNT_NAMES as $name) {
            if (!isset($diff[$name[0]])) {
                continue;
            }
            $value = $diff[$name[0]]->negative();
            if ($total instanceof Money) {
                $total = $total->add($value);
            } else {
                $total = $value;
            }
            $transactions->addValue($name[1], $this->formatPhpMoney($value));
        }
        if ($total instanceof Money) {
            $transactions->setSummary('Net transactions', $this->formatPhpMoney($total));
        }
        $mainRow->addNestedRow($transactions);

        // Open/Close Balances
        $mainRow->addValue('Opening balance', $this->formatPhpMoney($this->startingBalance));
        $mainRow->setSummary('Closing balance', $this->formatPhpMoney($this->closingBalance));

        $financialReport->addRow($mainRow);

        $section = new Section('');
        $section->addGroup($financialReport);
        $this->report->addSection($section);
    }
}
