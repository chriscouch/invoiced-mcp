<?php

namespace App\Reports\PresetReports;

use App\Core\Ledger\Ledger;
use App\PaymentProcessing\Enums\MerchantAccountLedgerAccounts;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use App\Reports\Enums\ColumnType;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\FinancialReportColumn;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\Section;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Money\Money;

class MerchantAccountSummary extends AbstractReport
{
    public function __construct(
        Connection $database,
        ReportHelper $helper,
        private MerchantAccountLedger $ledger,
    ) {
        parent::__construct($database, $helper);
    }

    public static function getId(): string
    {
        return 'merchant_account_summary';
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
        return 'Merchant Account Summary';
    }

    protected function build(): void
    {
        $merchantAccount = $this->getMerchantAccount();
        if (!$merchantAccount instanceof MerchantAccount) {
            $this->noData();

            return;
        }

        $ledger = $this->ledger->getLedger($merchantAccount);

        $this->addOverviewGroup();

        $start = CarbonImmutable::createFromTimestamp($this->start)->subDay();
        $startingAccounts = $ledger->reporting->getAccountBalances($start);
        $diff = [];
        foreach ($startingAccounts as $row) {
            if ($row['name'] == MerchantAccountLedgerAccounts::MerchantAccount->value) {
                $this->startingBalance = $row['balance'];
            } else {
                $diff[$row['name']] = $row['balance'];
            }
        }

        $end = CarbonImmutable::createFromTimestamp($this->end);
        $endingAccounts = $ledger->reporting->getAccountBalances($end);
        foreach ($endingAccounts as $row) {
            if ($row['name'] == MerchantAccountLedgerAccounts::MerchantAccount->value) {
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

    private function getMerchantAccount(): ?MerchantAccount
    {
        return MerchantAccount::find($this->parameters['$merchantAccount']);
    }

    private function addOverviewGroup(): void
    {
        $overview = new KeyValueGroup();
        $section = new Section('');
        $section->addGroup($overview);
        $this->report->addSection($section);
    }

    private function noData(): void
    {
        $overview = new KeyValueGroup();
        $overview->addLine('No Data Available', '');
        $section = new Section('');
        $section->addGroup($overview);
        $this->report->addSection($section);
    }
}
