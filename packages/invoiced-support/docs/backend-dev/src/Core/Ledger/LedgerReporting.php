<?php

namespace App\Core\Ledger;

use App\Core\Ledger\ValueObjects\AccountingParty;
use App\Reports\ValueObjects\AgingBreakdown;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Money\Currency;
use Money\Money;

/**
 * This class can perform common ledger data queries.
 */
final class LedgerReporting
{
    public function __construct(private readonly Connection $database, private readonly Ledger $ledger)
    {
    }

    /**
     * Gets a list of balances for each account in the ledger
     * as of a given date, or now if no date is provided.
     *
     * @return array{name: string, balance: Money}[]
     */
    public function getAccountBalances(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();
        $sql = 'SELECT name,C.code,
           (SELECT IFNULL(SUM(CASE WHEN entry_type = "D" THEN amount ELSE -amount END), 0)
                FROM LedgerEntries
                JOIN LedgerTransactions T on LedgerEntries.transaction_id = T.id
                WHERE account_id = Accounts.id AND T.transaction_date <= :date) AS balance
    FROM Accounts JOIN Currencies C ON C.id=currency_id WHERE ledger_id=:ledgerId ORDER BY name';

        $balances = $this->database->fetchAllAssociative($sql, [
            'ledgerId' => $this->ledger->id,
            'date' => $date->toDateString(),
        ]);

        $result = [];
        foreach ($balances as $row) {
            $result[] = [
                'name' => $row['name'],
                'balance' => new Money($row['balance'], new Currency($row['code'])),
            ];
        }

        return $result;
    }

    /**
     * Gets the balance of an account in the ledger
     * as of a given date, or now if no date is provided.
     */
    public function getAccountBalance(string $account, ?CarbonImmutable $date = null): Money
    {
        $date ??= CarbonImmutable::now();
        $accountId = $this->ledger->chartOfAccounts->getId($account);
        $currencyId = $this->ledger->chartOfAccounts->getCurrencyId($account);

        $sql = 'SELECT IFNULL(SUM(CASE WHEN entry_type = "D" THEN amount ELSE -amount END), 0)
                FROM LedgerEntries
                JOIN LedgerTransactions T on LedgerEntries.transaction_id = T.id
                WHERE account_id = :accountId AND T.transaction_date <= :date';

        $result = $this->database->fetchOne($sql, [
            'date' => $date->toDateString(),
            'accountId' => $accountId,
        ]);

        return new Money($result, new Currency($this->ledger->currencyRepository->getISO($currencyId)));
    }

    public function getAccountingPartyBalance(AccountingParty $party, string $account, ?CarbonImmutable $date = null): Money
    {
        $date ??= CarbonImmutable::now();
        $accountId = $this->ledger->chartOfAccounts->getId($account);
        $currencyId = $this->ledger->chartOfAccounts->getCurrencyId($account);

        $sql = 'SELECT IFNULL(SUM(CASE WHEN E.entry_type = "D" THEN E.amount ELSE -E.amount END), 0)
FROM LedgerEntries E
         JOIN LedgerTransactions T on E.transaction_id = T.id
WHERE T.transaction_date <= :date
  AND E.account_id = :accountId
  AND E.party_id = :partyId
  AND E.party_type = :partyType';

        $result = $this->database->fetchOne($sql, [
            'date' => $date->toDateString(),
            'accountId' => $accountId,
            'partyId' => $party->id,
            'partyType' => $party->type->value,
        ]);

        return new Money($result, new Currency($this->ledger->currencyRepository->getISO($currencyId)));
    }

    public function getDocumentBalance(int $documentId, string $account, ?CarbonImmutable $date = null): Money
    {
        $date ??= CarbonImmutable::now();
        $accountId = $this->ledger->chartOfAccounts->getId($account);
        $currencyId = $this->ledger->chartOfAccounts->getCurrencyId($account);

        $sql = 'SELECT IFNULL(SUM(CASE WHEN E.entry_type = "D" THEN E.amount ELSE -E.amount END), 0)
FROM LedgerEntries E
         JOIN Documents D on E.document_id = D.id
         JOIN LedgerTransactions T on E.transaction_id = T.id
WHERE T.transaction_date <= :date
  AND E.document_id = :documentId
  AND E.account_id = :accountId';

        $result = $this->database->fetchOne($sql, [
            'date' => $date->toDateString(),
            'accountId' => $accountId,
            'documentId' => $documentId,
        ]);

        return new Money($result, new Currency($this->ledger->currencyRepository->getISO($currencyId)));
    }

    public function getDocumentTransactions(int $documentId, string $account, ?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();
        $accountId = $this->ledger->chartOfAccounts->getId($account);
        $currencyId = $this->ledger->chartOfAccounts->getCurrencyId($account);

        $sql = 'SELECT  T.transaction_date, DT.name AS document_type, D.reference, SUM(IFNULL(CASE WHEN E.entry_type = "D" THEN E.amount ELSE -E.amount END, 0)) AS amount
FROM LedgerEntries E
         JOIN LedgerTransactions T on E.transaction_id = T.id
         JOIN Documents D on T.document_id = D.id
         JOIN DocumentTypes DT on D.document_type_id = DT.id
WHERE T.transaction_date <= :date
  AND E.document_id = :documentId
  AND E.account_id = :accountId
GROUP BY D.id, T.transaction_date
HAVING amount <> 0
ORDER BY T.transaction_date, T.id';

        $data = $this->database->fetchAllAssociative($sql, [
            'date' => $date,
            'documentId' => $documentId,
            'accountId' => $accountId,
        ]);

        $result = [];
        foreach ($data as $row) {
            $result[] = [
                'transaction_date' => $row['transaction_date'],
                'document_type' => $row['document_type'],
                'reference' => $row['reference'],
                'amount' => new Money($row['amount'], new Currency($this->ledger->currencyRepository->getISO($currencyId))),
            ];
        }

        return $result;
    }

    public function getAging(AgingBreakdown $agingBreakdown, string $account, ?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();

        $accountId = $this->ledger->chartOfAccounts->getId($account);
        $currencyId = $this->ledger->chartOfAccounts->getCurrencyId($account);

        $sql = 'SELECT '.$this->getAgingSelectClause($agingBreakdown).'FROM (
         SELECT DATEDIFF(:date, D.'.$agingBreakdown->dateColumn.')                   AS age,
                IFNULL(SUM(CASE WHEN E.entry_type = "D" THEN E.amount ELSE -E.amount END), 0) AS balance
         FROM LedgerEntries E
                  JOIN Documents D on E.document_id = D.id
         WHERE D.date <= :date
           AND E.document_id IS NOT NULL
           AND E.account_id = :accountId
         GROUP BY E.document_id
         HAVING balance <> 0
     ) a';

        $data = $this->database->fetchAssociative($sql, [
            'date' => $date->toDateString(),
            'accountId' => $accountId,
        ]);

        $currency = new Currency($this->ledger->currencyRepository->getISO($currencyId));

        $result = [];
        foreach ($agingBreakdown->getBuckets() as $i => $bucket) {
            $result[] = [
                'age_lower' => $bucket['lower'],
                'amount' => new Money((int) $data['age'.$i], $currency), /* @phpstan-ignore-line */
                'count' => (int) $data['age'.$i.'_count'], /* @phpstan-ignore-line */
            ];
        }

        return $result;
    }

    /**
     * Gets the select columns for the aging buckets.
     */
    private function getAgingSelectClause(AgingBreakdown $agingBreakdown): string
    {
        $select = [];
        $agingBuckets = $agingBreakdown->getBuckets();
        foreach ($agingBuckets as $i => $bucket) {
            $k = "age$i";
            if (-1 == $bucket['lower']) {
                $select[] = 'SUM(CASE WHEN age <= -1 OR age IS NULL'.
                    ' THEN balance ELSE 0 END) AS "'.$k.'"';
                $select[] = 'SUM(CASE WHEN age <= -1 OR age IS NULL'.
                    ' THEN 1 ELSE 0 END) AS "'.$k.'_count"';
            } elseif ($i == count($agingBuckets) - 1) {
                $select[] = 'SUM(CASE WHEN age >= '.$bucket['lower'].
                    ' THEN balance ELSE 0 END) AS "'.$k.'"';
                $select[] = 'SUM(CASE WHEN age >= '.$bucket['lower'].
                    ' THEN 1 ELSE 0 END) AS "'.$k.'_count"';
            } else {
                $select[] = 'SUM(CASE WHEN age BETWEEN '.$bucket['lower'].' AND '.$bucket['upper'].
                    ' THEN balance ELSE 0 END) AS "'.$k.'"';
                $select[] = 'SUM(CASE WHEN age BETWEEN '.$bucket['lower'].' AND '.$bucket['upper'].
                    ' THEN 1 ELSE 0 END) AS "'.$k.'_count"';
            }
        }

        return implode(',', $select);
    }

    public function getPartyBalances(string $account, ?int $count = null, ?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();

        $accountId = $this->ledger->chartOfAccounts->getId($account);
        $currencyId = $this->ledger->chartOfAccounts->getCurrencyId($account);

        $sql = 'SELECT E.party_id,
       IFNULL(SUM(CASE WHEN E.entry_type = "D" THEN E.amount ELSE -E.amount END), 0) AS balance
FROM LedgerEntries E
         JOIN LedgerTransactions T on E.transaction_id = T.id
WHERE T.transaction_date <= :date
  AND E.account_id = :accountId
GROUP BY E.party_id
HAVING balance <> 0
ORDER BY balance DESC';

        if ($count) {
            $sql .= ' LIMIT '.$count;
        }

        $data = $this->database->fetchAllAssociative($sql, [
            'date' => $date->toDateString(),
            'accountId' => $accountId,
        ]);
        foreach ($data as &$row) {
            $row['balance'] = new Money($row['balance'], new Currency($this->ledger->currencyRepository->getISO($currencyId)));
        }

        return $data;
    }
}
