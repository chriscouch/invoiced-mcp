<?php

namespace App\Integrations\Adyen\ReportHandler;

use App\Core\Csv\CsvWriter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Mailer\Mailer;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Interfaces\ReportHandlerInterface;
use App\Integrations\Adyen\Models\AdyenAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use mikehaertl\tmp\File;
use RuntimeException;

class BalanceReportHandler implements ReportHandlerInterface
{
    private const HEADER_LINE = [
        'Account Holder',
        'Balance Account',
        'Account Name',
        'Currency',
        'Closing Balance',
        'Closing Date',
    ];

    private array $negativeBalances = [];

    public function __construct(
        private bool $adyenLiveMode,
        private Mailer $mailer,
        private string $environment,
    ) {
    }

    public function handleRow(array $row): void
    {
        $closingBalance = Money::fromDecimal($row['closing_balance_currency'], (float) $row['closing_balance_amount']);
        if (!$closingBalance->isNegative()) {
            return;
        }

        // Special case for liable account
        $accountHolderId = $row['account_holder'];
        $liableAccount = AdyenConfiguration::getLiableAccountHolder($this->adyenLiveMode);
        if ($accountHolderId == $liableAccount) {
            $accountName = 'Flywire Liable Account';
        } else {
            $adyenAccount = AdyenAccount::queryWithoutMultitenancyUnsafe()
                ->where('account_holder_id', $accountHolderId)
                ->oneOrNull();
            $accountName = $adyenAccount?->tenant()->name();
        }

        $closingDate = new CarbonImmutable($row['closing_date'], new CarbonTimeZone($row['closing_timezone']));

        // In order to not be annoying, only consider closing balances in the last 3 days
        if ('test' !== $this->environment && $closingDate->isBefore(CarbonImmutable::now()->subDays(3))) {
            return;
        }

        $this->negativeBalances[] = [
            $accountHolderId,
            $row['balance_account'],
            $accountName,
            $closingBalance->currency,
            $closingBalance->toDecimal(),
            $closingDate->format('Y-m-d'),
        ];
    }

    public function finish(): void
    {
        // Generate the report of accounts with a negative balance
        if (!$this->negativeBalances) {
            return;
        }

        $tmpFile = new File('', 'csv');
        $tmpFileName = $tmpFile->getFileName();
        $fp = fopen($tmpFileName, 'w');
        if (!$fp) {
            throw new RuntimeException('Could not open temp file');
        }

        $tmpFile = new File('', 'csv');
        $tmpFileName = $tmpFile->getFileName();
        $fp = fopen($tmpFileName, 'w');
        if (!$fp) {
            throw new RuntimeException('Could not open temp file');
        }

        CsvWriter::write($fp, self::HEADER_LINE);
        foreach ($this->negativeBalances as $row) {
            CsvWriter::write($fp, $row);
        }

        fclose($fp);

        // Send the alert to Slack (recent reports in live mode only)
        if (!$this->adyenLiveMode) {
            return;
        }

        $numNegative = count($this->negativeBalances);
        $this->mailer->send([
            'from_email' => 'no-reply@invoiced.com',
            'to' => [['email' => 'b2b-payfac-notificati-aaaaqfagorxgbzwrnrb7unxgrq@flywire.slack.com', 'name' => 'Invoiced Payment Ops']],
            'subject' => 'Balance accounts with negative balances',
            'text' => "Balance accounts with negative closing balances: $numNegative",
            'attachments' => [
                [
                    'name' => 'negative_balance_accounts.csv',
                    'type' => 'text/csv',
                    'content' => base64_encode((string) file_get_contents($tmpFileName)),
                ],
            ],
        ]);
    }
}
