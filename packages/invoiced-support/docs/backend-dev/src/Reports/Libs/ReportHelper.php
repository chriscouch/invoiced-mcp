<?php

namespace App\Reports\Libs;

use Doctrine\DBAL\Connection;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

final class ReportHelper implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    private string $currentTimezone = 'UTC'; // the initial time zone for our application
    private bool $setReadOnly = false;

    public function __construct(private Connection $database)
    {
    }

    public function switchTimezone(string $timezone): void
    {
        $this->setReadOnly();

        $timezone = $timezone ?: 'UTC';

        // skip if not changing time zones
        if ($this->currentTimezone == $timezone) {
            return;
        }

        // sync up the db with the time zone
        try {
            // NOTE: we are using the name of the timezone (i.e. America/Chicago)
            // instead of the offset (i.e. -5:00) because the named time zone
            // correctly handles Daylight Savings Time
            $this->database->executeStatement("SET time_zone='$timezone';");
            $this->currentTimezone = $timezone;
        } catch (Exception $e) {
            $this->logger->error('Unable to select reporting timezone', ['exception' => $e]);
        }
    }

    /**
     * Set the transaction to read only
     * which performs better.
     */
    private function setReadOnly(): void
    {
        if (!$this->setReadOnly) {
            $this->setReadOnly = true;
            try {
                $this->database->executeStatement('SET SESSION TRANSACTION READ ONLY');
            } catch (Exception $e) {
                $this->logger->error('Unable to set transaction isolation level', ['exception' => $e]);
            }
        }
    }
}
