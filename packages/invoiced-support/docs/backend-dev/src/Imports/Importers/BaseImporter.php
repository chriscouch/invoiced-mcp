<?php

namespace App\Imports\Importers;

use App\Imports\Interfaces\ImporterInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Provides a foundation for importers to build on.
 */
abstract class BaseImporter implements ImporterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected bool $hasMore = false;
    protected array $hasMoreOptions = [];

    /**
     * Checks if the importer has another batch to process.
     */
    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    /**
     * Gets the options for the next import batch.
     */
    public function hasMoreOptions(): array
    {
        return $this->hasMoreOptions;
    }
}
