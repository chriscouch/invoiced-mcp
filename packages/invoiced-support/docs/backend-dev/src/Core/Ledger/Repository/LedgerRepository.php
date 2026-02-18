<?php

namespace App\Core\Ledger\Repository;

use App\Core\Ledger\Ledger;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class LedgerRepository
{
    public function __construct(private Connection $database)
    {
    }

    /**
     * Locates or creates a new ledger identified by its name.
     */
    public function findOrCreate(string $name, string $baseCurrency): Ledger
    {
        if ($ledger = $this->find($name)) {
            return $ledger;
        }

        return $this->create($name, $baseCurrency);
    }

    public function find(string $name): ?Ledger
    {
        $details = $this->database->fetchAssociative('SELECT L.id,C.code AS currency FROM Ledgers L JOIN Currencies C ON L.base_currency_id=C.id WHERE L.name=:name', [
            'name' => $name,
        ]);

        if (!is_array($details)) {
            return null;
        }

        return new Ledger($this->database, $details['id'], $details['currency']);
    }

    public function create(string $name, string $baseCurrency): Ledger
    {
        $baseCurrency = strtoupper($baseCurrency);
        $currencyRepository = new CurrencyRepository($this->database);
        // The "UPDATE id = LAST_INSERT_ID(id)" is needed to make the next LAST_INSERT_ID call return the correct value
        $this->database->executeStatement('INSERT INTO Ledgers (name, base_currency_id, created_at) VALUES (:name, :currencyId, :createdAt) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), name = :name', [
            'name' => $name,
            'currencyId' => $currencyRepository->getId($baseCurrency),
            'createdAt' => CarbonImmutable::now()->toDateTimeString(),
        ]);
        $ledgerId = (int) $this->database->lastInsertId();

        return new Ledger($this->database, $ledgerId, $baseCurrency, $currencyRepository);
    }
}
