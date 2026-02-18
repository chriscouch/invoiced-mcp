<?php

namespace App\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class IpInfoLookup implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CACHE_TTL = 31536000;

    public function __construct(
        private CacheInterface $cache,
        private HttpClientInterface $httpClient,
        private string $ipinfoKey,
    ) {
    }

    public function makeIpInfoLink(?string $ip): ?string
    {
        if (!$ip) {
            return $ip;
        }

        $description = $ip;
        if ($ipInfo = $this->get($ip)) {
            $city = $ipInfo['city'] ?? '';
            $region = $ipInfo['region'] ?? '';
            $country = $ipInfo['country'] ?? '';
            $country = 'US' != $country ? $country : null;
            $location = implode(', ', array_filter([$city, $region, $country]));
            $location = $location ?: 'Unknown';
            if ($ipInfo['bogon'] ?? false) {
                $location = 'Bogon';
            }
            $description .= " ($location)";
        }

        return '<a href="https://ipinfo.io/'.$ip.'" target="_blank">'.$description.'</a>';
    }

    /**
     * Looks up an IP address from ipinfo.io.
     */
    public function get(string $ip): ?array
    {
        // validate the IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (!$this->ipinfoKey) {
            return null;
        }

        // check for cached value
        $cacheKey = 'ipinfo.'.str_replace(':', '_', $ip);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($ip) {
            try {
                $response = $this->httpClient->request('GET', "https://ipinfo.io/$ip", [
                    'query' => [
                        'token' => $this->ipinfoKey,
                    ],
                ]);
                $item->expiresAfter(self::CACHE_TTL);

                return $response->toArray();
            } catch (Throwable $e) {
                if ($this->logger) {
                    $this->logger->error('Could not fetch IP info', ['exception' => $e]);
                }

                // on failure cache this for one minute instead of permanently
                $item->expiresAfter(60);

                return null;
            }
        });
    }
}
