<?php

namespace App\Core\Search\Interfaces;

use App\Companies\Models\Company;
use App\Core\Search\Exceptions\SearchException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This is the contract for a search strategy client.
 */
interface DriverInterface
{
    /**
     * Gets an index instance given a company and model type.
     * This function SHOULD NOT perform any callouts to the
     * search backend.
     */
    public function getIndex(Company $company, string $modelClass): IndexInterface;

    /**
     * Creates an index for an index name and model.
     *
     * @param string|null          $name   desired search index name
     * @param bool                 $dryRun does not actually write the changes
     * @param OutputInterface|null $output console output
     */
    public function createIndex(Company $company, string $modelClass, ?string $name = null, bool $dryRun = false, ?OutputInterface $output = null): IndexInterface;

    /**
     * Performs a search using the search backend. This function might
     * delegate the search to multiple physical search indexes depending on
     * the implementation.
     *
     * @throws SearchException if the search query is invalid. This exception
     *                         should not be thrown if the query is valid with no results. If there is
     *                         an issue connecting with the search backend then this function should log
     *                         and return an empty array instead of throwing an exception.
     */
    public function search(Company $company, string $query, ?string $modelClass, int $numResults): array;
}
