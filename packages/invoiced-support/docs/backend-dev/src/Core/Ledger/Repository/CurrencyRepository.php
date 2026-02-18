<?php

namespace App\Core\Ledger\Repository;

use App\Core\Ledger\Exception\LedgerException;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Exchanger\Contract\ExchangeRate;
use Exchanger\CurrencyPair;
use Exchanger\Exchanger;
use Exchanger\ExchangeRate as ExchangeRateObj;
use Exchanger\HistoricalExchangeRateQuery;

final class CurrencyRepository
{
    private array $currencies = [];
    /** @var ExchangeRate[] */
    private array $rates = [];

    public function __construct(private Connection $database)
    {
    }

    /**
     * Creates a new currency in the database.
     */
    public function create(string $currency, int $numericCode, int $minorUnit): void
    {
        $this->database->executeStatement("INSERT INTO Currencies (code, numeric_code, num_decimals) VALUES ('$currency', $numericCode, $minorUnit) ON DUPLICATE KEY UPDATE code=VALUES(code), numeric_code=VALUES(numeric_code), num_decimals=VALUES(num_decimals)");
        $this->currencies[$currency] = $this->database->lastInsertId();
    }

    /**
     * Gets the database ID of a currency given its 3-digit ISO code, e.g. USD.
     *
     * @throws LedgerException
     */
    public function getId(string $currency): int
    {
        if (!isset($this->currencies[$currency])) {
            $id = $this->database->fetchOne('SELECT id FROM Currencies WHERE code=:code', [
                'code' => $currency,
            ]);
            if (!$id) {
                throw new LedgerException('Currency does not exist: '.$currency);
            }

            $this->currencies[$currency] = $id;
        }

        return $this->currencies[$currency];
    }

    /**
     * Gets the 3-digit ISO code, e.g. USD. of a currency given its database ID.
     *
     * @throws LedgerException
     *
     * @return non-empty-string
     */
    public function getISO(int $id): string
    {
        if ($currency = array_search($id, $this->currencies)) {
            return (string) $currency;
        }
        $currency = $this->database->fetchOne('SELECT code FROM Currencies WHERE id=:id', [
            'id' => $id,
        ]);
        if (!$currency) {
            throw new LedgerException('Currency does not exist: '.$id);
        }

        $this->currencies[$currency] = $id;

        return $currency;
    }

    /**
     * Gets the exchange rate for a currency pair on a given date.
     * This function uses multiple levels of caching to minimize
     * the requests to the currency conversion service.
     */
    public function getExchangeRate(Exchanger $exchanger, CurrencyPair $pair, CarbonImmutable $date): ExchangeRate
    {
        if ($pair->isIdentical()) {
            return new ExchangeRateObj($pair, 1.0, $date, 'identity');
        }

        // First check the in-memory cache
        $hashKey = $pair->getBaseCurrency().'/'.$pair->getQuoteCurrency().$date->toDateString();
        if (isset($this->rates[$hashKey])) {
            return $this->rates[$hashKey];
        }

        // Then check the database
        $cachedRate = $this->database->fetchOne('SELECT exchange_rate FROM ExchangeRates WHERE base_currency_id=? AND target_currency_id=? AND `date`=?', [
            $this->getId($pair->getBaseCurrency()),
            $this->getId($pair->getQuoteCurrency()),
            $date->toDateString(),
        ]);
        if ($cachedRate) {
            $this->rates[$hashKey] = new ExchangeRateObj($pair, $cachedRate, $date, 'database');

            return $this->rates[$hashKey];
        }

        // Finally query the exchange rate service
        $query = new HistoricalExchangeRateQuery($pair, $date);
        $rate = $exchanger->getExchangeRate($query);

        // Save the rate to reliably return the same value later
        $this->rates[$hashKey] = $rate;
        $this->database->insert('ExchangeRates', [
            'base_currency_id' => $this->getId($pair->getBaseCurrency()),
            'target_currency_id' => $this->getId($pair->getQuoteCurrency()),
            'date' => $date->toDateString(),
            'exchange_rate' => $rate->getValue(),
        ]);

        return $rate;
    }
}
