<?php

namespace App\Core\Utils;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class IpLookup implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const CACHE_TTL = 31536000;

    public function __construct(private CacheInterface $cache, private string $ipinfoKey)
    {
    }

    /**
     * Looks up an IP address.
     */
    public function get(string $ip): ?object
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
            // lookup the rate from ipinfo.io
            $url = "https://ipinfo.io/$ip?token=".$this->ipinfoKey;

            try {
                $json = (string) file_get_contents($url);

                $item->expiresAfter(self::CACHE_TTL);

                return json_decode($json);
            } catch (Throwable $e) {
                $this->logger->error('Could not fetch IP info', ['exception' => $e]);

                // on failure cache this for one minute instead of permanently
                $item->expiresAfter(60);

                return null;
            }
        });
    }
}
