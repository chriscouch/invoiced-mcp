<?php

namespace App\Reports\ReportBuilder;

use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Given a report configuration, fetches the
 * correct data from the database.
 */
final class DataService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private Connection $database)
    {
    }

    /**
     * Fetches the results to a report data query from
     * the reporting database.
     *
     * @throws ReportException
     */
    public function fetchData(DataQuery $query, array $parameters): array
    {
        // convert the report configuration to a parameterized sql query
        $context = new SqlContext($parameters);
        $sql = SqlGenerator::generate($query, $context);

        // execute the query
        try {
            $this->logger->info('Generated SQL', [
                'sql' => $sql,
            ]);

            return $this->database->fetchAllAssociative($sql, $context->getParams());
        } catch (Throwable $e) {
            $this->logger->error('SQL error when building report', [
                'exception' => $e,
                'sql' => $sql,
            ]);

            $msg = 'An error occurred when building your report.';
            if ($e instanceof Exception\DriverException && '70100' == $e->getSQLState()) {
                $msg = 'Your report query has timed out.';
            }

            throw new ReportException($msg);
        }
    }
}
