<?php

namespace App\Integrations\ChartMogul\Syncs;

use App\Integrations\ChartMogul\Interfaces\ChartMogulSyncInterface;
use Carbon\CarbonImmutable;
use ChartMogul\Customer as ChartMogulCustomer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractSync implements ChartMogulSyncInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static array $customers = [];

    protected static array $plans = [];

    public static function getDefaultPriority(): int
    {
        return 10;
    }

    /**
     * Looks up a customer on ChartMogul using the Invoiced customer ID.
     */
    protected function lookupCustomer(int $id, bool $cache = true): ?ChartMogulCustomer
    {
        if (isset(self::$customers[$id])) {
            return self::$customers[$id];
        }

        $result = ChartMogulCustomer::findByExternalId((string) $id);
        // for some reason this function returns false instead of null
        if (!$result) {
            return null;
        }

        if ($cache) {
            self::$customers[$id] = $result;
        }

        return $result;
    }

    protected function clearCache(): void
    {
        self::$customers = [];
        self::$plans = [];
    }

    /**
     * Converts a UNIX timestamp to a Chartmogul date string.
     */
    protected function timestampToDate(?int $timestamp): ?string
    {
        if (!$timestamp) {
            return null;
        }

        return date('c', $timestamp);
    }

    /**
     * Converts a DateTime instance to a Chartmogul date string.
     */
    protected function datetimeToDate(?CarbonImmutable $date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date->format('c');
    }
}
