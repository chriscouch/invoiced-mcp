<?php

namespace App\Core\Search\Interfaces;

use App\Companies\Models\Company;
use Symfony\Component\Console\Output\OutputInterface;

interface ReindexingStrategyInterface
{
    /**
     * Runs the reindexing strategy.
     *
     * @param bool                 $dryRun does not actually write the changes
     * @param OutputInterface|null $output console output
     */
    public function run(Company $company, string $modelClass, IndexInterface $index, bool $dryRun = false, OutputInterface $output = null): void;
}
