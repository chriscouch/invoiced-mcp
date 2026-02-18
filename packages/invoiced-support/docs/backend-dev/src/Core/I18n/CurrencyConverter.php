<?php

namespace App\Core\I18n;

use App\Core\I18n\Exception\CurrencyConversionException;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class CurrencyConverter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public static array $rates = [];

    public function __construct(private CacheInterface $cache, private string $key)
    {
    }

    /**
     * Converts a money amount to the desired currency.
     *
     * @throws CurrencyConversionException
     */
    public function convert(Money $amount, string $targetCurrency): Money
    {
        $conversionRate = $this->getConversionRateFrom($amount->currency, $targetCurrency);

        $convertedAmount = $amount->toDecimal() * $conversionRate;

        return Money::fromDecimal($targetCurrency, $convertedAmount);
    }

    /**
     * Looks up a conversion rate from the given currency to the base currency.
     *
     * @param string $currency currency code
     * @param string $base     base currency code
     *
     * @throws CurrencyConversionException
     */
    public function getConversionRateFrom(string $currency, string $base): float
    {
        // validate the currency code
        $currency = strtoupper($currency);
        if (3 != strlen($currency)) {
            throw new CurrencyConversionException('Invalid currency code: '.$currency);
        }

        // conversion rate to itself is always 1
        $base = strtoupper($base);
        if ($currency === $base) {
            return 1;
        }

        // check if the value is cached in-memory
        $key = "$base$currency";
        if (isset(self::$rates[$key])) {
            return self::$rates[$key];
        }

        // check for a cached value
        $cacheKey = 'currency_conversion_rate_'.$key;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currency, $base, $key) {
            // look up the rate from currencylayer
            try {
                $client = new Client([
                    'headers' => ['User-Agent' => 'Invoiced/1.0'],
                ]);
                $response = $client->get('https://apilayer.net/api/live', [
                    'query' => [
                        'access_key' => $this->key,
                        'currencies' => $currency,
                        'source' => $base,
                        'format' => 1,
                    ],
                ]);

                $result = json_decode($response->getBody());
            } catch (Throwable $e) {
                throw new CurrencyConversionException('Could convert currency from '.$base.' to '.$currency.': '.$e->getMessage());
            }

            if (!$result->success) {
                throw new CurrencyConversionException('Could convert currency from '.$base.' to '.$currency.': '.$response->getBody());
            }

            // this rate will tell us how many 1 base is worth
            // for 1 unit of our given currency
            $rate = $result->quotes->$key;

            // need to invert in order to get the amount of base
            // 1 unit of our given currency is worth
            $rate = 1 / $rate;

            // cache the result
            self::$rates[$key] = $rate;
            $item->expiresAt(CarbonImmutable::now()->endOfDay());

            return $rate;
        });
    }
}
