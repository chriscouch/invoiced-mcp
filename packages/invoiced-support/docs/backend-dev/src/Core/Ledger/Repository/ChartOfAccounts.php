<?php

namespace App\Core\Ledger\Repository;

use App\Core\Ledger\Enums\AccountType;
use App\Core\Ledger\Exception\LedgerException;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

final class ChartOfAccounts
{
    private array $accounts = [];
    private array $currencies = [];

    public function __construct(
        private Connection $database,
        private int $ledgerId,
        private CurrencyRepository $currencyRepository,
    ) {
    }

    /**
     * Gets the database ID of an account given the name.
     */
    public function getId(string $name): int
    {
        $key = $name;
        if (!isset($this->accounts[$key])) {
            $result = $this->database->fetchAssociative('SELECT id, currency_id FROM Accounts WHERE ledger_id=:ledgerId AND name=:name', [
                'ledgerId' => $this->ledgerId,
                'name' => $name,
            ]);
            if (!$result) {
                throw new LedgerException('Account does not exist: '.$name);
            }

            $this->accounts[$key] = $result['id'];
            $this->currencies[$result['id']] = $result['currency_id'];
        }

        return $this->accounts[$key];
    }

    /**
     * Get currency id by account name.
     */
    public function getCurrencyId(string $name): int
    {
        $accountId = $this->getId($name);
        if (!isset($this->currencies[$accountId])) {
            throw new LedgerException('Account does not have currency: '.$name);
        }

        return $this->currencies[$accountId];
    }

    /**
     * Finds or creates an account identified by the name.
     */
    public function findOrCreate(string $name, AccountType $type, string $currency): int
    {
        try {
            return $this->getId($name);
        } catch (LedgerException) {
            // exception means not found
        }

        $currencyId = $this->currencyRepository->getId($currency);
        $this->database->executeStatement('INSERT INTO Accounts (ledger_id, name, account_type, currency_id, created_at) VALUES (:ledgerId, :name, :accountType, :currencyId, :createdAt) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = :name', [
            'ledgerId' => $this->ledgerId,
            'name' => $name,
            'accountType' => $type->value,
            'currencyId' => $currencyId,
            'createdAt' => CarbonImmutable::now()->toDateTimeString(),
        ]);
        $key = $name;
        $this->accounts[$key] = (int) $this->database->lastInsertId();
        $this->currencies[$this->accounts[$key]] = $currencyId;

        return $this->accounts[$key];
    }

    public function getAll(): array
    {
        $res = $this->database->fetchAllAssociativeIndexed('SELECT id, name, account_type, currency_id FROM Accounts WHERE ledger_id=:ledgerId', [
            'ledgerId' => $this->ledgerId,
        ]);

        foreach ($res as $id => $result) {
            $this->accounts[$result['name']] = $id;
            $this->currencies[$id] = $result['currency_id'];
        }

        return $res;
    }
}
