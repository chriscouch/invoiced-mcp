<?php

namespace App\Core\Utils;

use Doctrine\DBAL\Connection;

/**
 * Utilities to work with IP address related date.
 */
class IpUtilities
{
    /**
     * Checks if an IP address is in a given CIDR block.
     *
     * @param string $ip   ipv4 address that we are checking
     * @param string $net  base ipv4 address
     * @param int    $mask CIDR mask, i.e. 24
     */
    public static function ipInCidr(string $ip, string $net, int $mask): bool
    {
        return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($net);
    }

    /**
     * Check the IP address against the block list.
     */
    public static function isBlocked(string $ip, Connection $database): bool
    {
        $count = $database->fetchOne('SELECT COUNT(*) FROM BlockListIpAddresses WHERE ip=?', [$ip]);

        return $count > 0;
    }

    /**
     * Adds an IP address against the block list.
     */
    public static function blockIp(string $ip, Connection $database): void
    {
        $database->executeStatement('INSERT IGNORE INTO BlockListIpAddresses (ip) VALUES (?)', [$ip]);
    }
}
