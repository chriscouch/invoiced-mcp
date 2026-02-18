<?php

namespace App\Core\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Exception;
use Generator;

/**
 * Collection of database helper methods for commonly performed operations.
 */
class DatabaseHelper
{
    /**
     * Performs a multi-line bulk insert into the database.
     *
     * @param array $data Flat array of all rows x columns that is numerically
     *                    indexed for parameter injection. The order of each row
     *                    should match the ordering of the given columns.
     *
     * @throws DBALException
     *
     * @return string ID of the first inserted row
     */
    public static function bulkInsert(Connection $database, string $table, array $columns, array $data): string
    {
        // Build the INSERT SQL statement
        $numColumns = count($columns);
        $numRows = count($data) / $numColumns;
        foreach ($columns as &$column) {
            $column = $database->quoteIdentifier($column);
        }

        $sql = 'INSERT INTO '.$table.' ('.join(',', $columns).') VALUES ';
        $rowPlaceholder = '('.join(',', array_fill(0, $numColumns, '?')).')';
        $sql .= join(',', array_fill(0, (int) $numRows, $rowPlaceholder));

        // Execute it in the database
        $database->executeStatement($sql, $data);

        // Return the first inserted ID
        return (string) $database->lastInsertId();
    }

    /**
     * Deletes potentially large amounts of data in chunks that
     * reduces database load and replication lag.
     *
     * Assumptions:
     * - Table has an integer column specified in input parameters list (defaults to id)
     *
     * Based on: http://mysql.rjweb.org/doc.php/deletebig
     *
     * @param bool $stopAfterNoneAffected When true, this will stop after any delete operation which has no
     *                                    affected rows. This should only be used on tables in which the WHERE
     *                                    condition is a monotonically increasing.
     *
     * @return int # affected rows
     */
    public static function bigDelete(Connection $database, string $table, string $where, int $chunkSize = 1000, bool $stopAfterNoneAffected = false, string $id = 'id'): int
    {
        // Safety measure to prevent deleting entire table
        if (!$where) {
            throw new Exception('Failed to specify WHERE condition for big delete operation');
        }

        $totalAffected = 0;
        $rangeStartId = $database->fetchOne('SELECT MIN('.$id.') FROM '.$table);
        $hasMore = $rangeStartId > 0;
        while ($hasMore) {
            $rangeEndId = $database->fetchOne("SELECT $id FROM $table WHERE $id >= $rangeStartId ORDER BY $id LIMIT $chunkSize,1");
            if (!$rangeEndId) {
                $hasMore = false; // last chunk
            } else {
                $affected = $database->executeStatement("DELETE FROM $table WHERE $id BETWEEN $rangeStartId AND $rangeEndId AND $where");
                // Stop early if no rows deleted and we know we do not need to keep looking
                if ($stopAfterNoneAffected && 0 == $affected) {
                    return $totalAffected;
                }
                $totalAffected += $affected;
                $rangeStartId = $rangeEndId;
                sleep(1); // be a nice guy, especially in replication
            }
        }

        // Last chunk
        if ($rangeStartId > 0) {
            $totalAffected += $database->executeStatement("DELETE FROM $table WHERE $id >= $rangeStartId AND $where");
        }

        return $totalAffected;
    }

    /**
     * Updates potentially large amounts of data in chunks that
     * reduces database load and replication lag.
     *
     * Assumptions:
     * - Table has an integer column specified in input parameters list (defaults to id)
     *
     * Based on: http://mysql.rjweb.org/doc.php/deletebig
     *
     * @return int # affected rows
     */
    public static function bigUpdate(Connection $database, string $table, string $set, string $where, int $chunkSize = 1000, string $id = 'id', string $join = null): int
    {
        // Safety measure to prevent deleting entire table
        if (!$where) {
            throw new Exception('Failed to specify WHERE condition for big delete operation');
        }

        $totalAffected = 0;
        $rangeEndId = 0;
        while (true) {
            $qry = "SELECT MIN($id), MAX($id)  FROM (SELECT $id  FROM $table ";
            if ($join) {
                $qry .= " JOIN $join ";
            }
            $qry .= " WHERE $where AND $id > $rangeEndId ORDER BY $id LIMIT $chunkSize) a";
            $range = $database->fetchNumeric($qry);
            if (!$range) {
                return $totalAffected;
            }
            [$rangeStartId, $rangeEndId] = $range;
            if (!$rangeStartId || !$rangeEndId) {
                return $totalAffected;
            }
            $qry = "UPDATE $table SET $set WHERE $id BETWEEN $rangeStartId AND $rangeEndId AND $where";
            $totalAffected += $database->executeStatement($qry);
            sleep(1); // be a nice guy, especially in replication
        }
    }

    public static function efficientBigDelete(Connection $database, string $table, string $where, int $chunkSize = 1000, string $id = 'id'): int
    {
        // Safety measure to prevent deleting entire table
        if (!$where) {
            throw new Exception('Failed to specify WHERE condition for big delete operation');
        }

        $totalAffected = 0;
        while (true) {
            $qry = "SELECT MIN($id), MAX($id)  FROM (SELECT $id  FROM $table WHERE $where ORDER BY $id LIMIT $chunkSize) a";
            $range = $database->fetchNumeric($qry);
            if (!$range) {
                return $totalAffected;
            }
            [$rangeStartId, $rangeEndId] = $range;
            if (!$rangeStartId || !$rangeEndId) {
                return $totalAffected;
            }
            $qry = "DELETE FROM $table WHERE $id BETWEEN '$rangeStartId' AND '$rangeEndId' AND $where";
            $totalAffected += $database->executeStatement($qry);
            sleep(1); // be a nice guy, especially in replication
        }
    }

    /**
     * Retrieves large amounts of data in chunks that
     * reduces database load.
     *
     * Assumptions:
     * - Table has an integer column specified in input parameters list (defaults to id)
     */
    public static function bigSelect(Connection $database, string $table, string $where, int $chunkSize = 1000, string $id = 'id'): Generator
    {
        // Safety measure to prevent selecting entire table
        if (!$where) {
            throw new Exception('Failed to specify WHERE condition for big select operation');
        }

        $rangeEndId = 0;
        while (true) {
            $qry = "SELECT MIN($id), MAX($id)  FROM (SELECT $id  FROM $table WHERE $where AND $id > $rangeEndId ORDER BY $id LIMIT $chunkSize) a";
            $range = $database->fetchNumeric($qry);
            if (!$range) {
                break;
            }
            [$rangeStartId, $rangeEndId] = $range;
            if (!$rangeStartId || !$rangeEndId) {
                break;
            }
            $qry = "SELECT * FROM $table WHERE $id BETWEEN $rangeStartId AND $rangeEndId AND $where";
            $data = $database->fetchAllAssociative($qry);
            foreach ($data as $row) {
                yield $row;
            }
            sleep(1); // be a nice guy, especially in replication
        }
    }
}
