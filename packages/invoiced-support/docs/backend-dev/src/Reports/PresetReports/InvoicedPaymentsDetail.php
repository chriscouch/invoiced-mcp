<?php

namespace App\Reports\PresetReports;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\WePay\Enums\PayoutAccounts;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Reports\ValueObjects\Section;
use Carbon\CarbonImmutable;

class InvoicedPaymentsDetail extends AbstractInvoicedPaymentsReport
{
    public static function getId(): string
    {
        return 'invoiced_payments_detail';
    }

    protected function getName(): string
    {
        return 'Invoiced Payments Detail';
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

        $start = CarbonImmutable::createFromTimestamp($this->start);
        $end = CarbonImmutable::createFromTimestamp($this->end);
        $sql = 'SELECT transaction_date AS date,
       DT.name AS type,
       SUM(CASE WHEN A.name = "'.PayoutAccounts::ProcessingFees->value.'" THEN 0 WHEN entry_type = "D" THEN -amount ELSE amount END) AS amount,
       SUM(CASE WHEN A.name <> "'.PayoutAccounts::ProcessingFees->value.'" THEN 0 WHEN entry_type = "D" THEN amount ELSE -amount END) AS fee,
       D.reference,
       T.description
FROM LedgerEntries E
         JOIN Accounts A on E.account_id = A.id
         JOIN LedgerTransactions T on E.transaction_id = T.id
         JOIN Documents D ON T.document_id = D.id
         JOIN DocumentTypes DT on D.document_type_id = DT.id
WHERE A.ledger_id = :ledgerId AND A.name <> "'.PayoutAccounts::MerchantAccount->value.'" AND transaction_date BETWEEN :start AND :end
GROUP BY T.transaction_date, T.document_id
HAVING amount <> 0';
        $transactions = $this->database->fetchAllAssociative($sql, [
            'ledgerId' => $ledger->id,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ]);

        $rows = [];
        foreach ($transactions as $transaction) {
            $amount = new Money($ledger->baseCurrency, (int) $transaction['amount']);
            $fee = new Money($ledger->baseCurrency, (int) $transaction['fee']);
            $rows[] = [
                (new CarbonImmutable($transaction['date']))->format($this->dateFormat),
                $transaction['type'],
                $transaction['description'],
                $transaction['reference'],
                $this->formatMoney($amount),
                $fee,
                $amount->subtract($fee),
            ];
        }

        $header = [
            ['name' => 'Date', 'type' => 'string'],
            ['name' => 'Type', 'type' => 'string'],
            ['name' => 'Description', 'type' => 'string'],
            ['name' => 'Transaction ID', 'type' => 'string'],
            ['name' => 'Amount', 'type' => 'string'],
            ['name' => 'Fee', 'type' => 'money'],
            ['name' => 'Net', 'type' => 'money'],
        ];
        $table = $this->buildTable($header, $rows);
        $section = new Section('');
        $section->addGroup($table);
        $this->report->addSection($section);
    }
}
