<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class CurrencyConverter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const ENDPOINT = 'https://apilayer.net/api/live';

    const CACHE_TTL = 86400;

    public static array $rates = [];

    private CacheInterface $cache;
    private HttpClientInterface $client;
    private string $key;

    public function __construct(CacheInterface $cache, HttpClientInterface $client, string $key)
    {
        $this->cache = $cache;
        $this->client = $client;
        $this->key = $key;
    }

    /**
     * Converts a money amount to the desired currency.
     */
    public function convert(string $currency, float $amount, string $targetCurrency): float
    {
        $conversionRate = $this->getConversionRateFrom($currency, $targetCurrency);

        return $amount * $conversionRate;
    }

    /**
     * Looks up a conversion rate from the given currency to the base currency.
     *
     * @param string $currency currency code
     * @param string $base     base currency code
     */
    public function getConversionRateFrom(string $currency, string $base = 'USD'): ?float
    {
        // validate the currency code
        $currency = strtoupper($currency);
        if (3 != strlen($currency)) {
            return null;
        }

        // conversion rate to itself is always 1
        $base = strtoupper($base);
        if ($currency === $base) {
            return 1;
        }

        // check if the value has already been cached
        $key = "$base$currency";
        if (isset(self::$rates[$key])) {
            return self::$rates[$key];
        }

        // check if redis has cached value
        $cacheKey = 'curr_convrate_'.$key;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currency, $base, $key) {
            // otherwise, lookup the rate from currencylayer
            $params = [
                'access_key' => $this->key,
                'currencies' => $currency,
                'source' => $base,
                'format' => 1,
            ];

            try {
                $response = $this->client->request('GET', self::ENDPOINT, [
                    'headers' => ['User-Agent' => 'Invoiced/1.0'],
                    'query' => $params,
                ]);

                $json = json_decode($response->getContent());

                if (!$json->success) {
                    if ($this->logger) {
                        $this->logger->error('Could not fetch currency conversion', ['response' => $response->getContent()]);
                    }

                    return null;
                }

                // this rate will tell us how many 1 base is worth
                // for 1 unit of our given currency
                $rate = $json->quotes->$key;

                // need to invert in order to get the amount of base
                // 1 unit of our given currency is worth
                $rate = 1 / $rate;

                // cache the result
                self::$rates[$key] = $rate;
                $item->expiresAfter(self::CACHE_TTL);

                return $rate;
            } catch (Throwable $e) {
                if ($this->logger) {
                    $this->logger->error('Could not fetch currency conversion', ['exception' => $e]);
                }
            }

            return null;
        });
    }
}
