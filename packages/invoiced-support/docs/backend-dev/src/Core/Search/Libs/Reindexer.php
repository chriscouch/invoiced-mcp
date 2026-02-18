<?php

namespace App\Core\Search\Libs;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Search\Interfaces\DriverInterface;
use App\Core\Search\Interfaces\ReindexingStrategyInterface;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Model;
use Symfony\Component\Console\Output\OutputInterface;

class Reindexer
{
    public function __construct(private Search $search, private IndexRegistry $registry, private TenantContext $tenant)
    {
    }

    /**
     * Rebuilds the search index for a given account.
     *
     * @param ReindexingStrategyInterface $strategy update strategy
     * @param bool                        $dryRun   does not actually write the changes
     * @param OutputInterface             $output   console output
     */
    public function run(Company $company, ReindexingStrategyInterface $strategy, bool $dryRun = false, OutputInterface $output = null, ?string $driver = null, string $indexName = 'all'): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        if ($output) {
            $output->writeln("Building search index for company # {$company->id()}");
        }

        $start = microtime(true);

        $driver = $this->search->getDriver($company, $driver);

        // sync a given object
        if ('all' == $indexName) {
            $objects = $this->registry->getIndexableObjectsForCompany($company);
        } else {
            $modelClass = ObjectType::fromTypeName($indexName)->modelClass();
            $objects = [$modelClass];
        }

        foreach ($objects as $modelClass) {
            if ($this->shouldRunSync($company, $modelClass, $driver)) {
                $this->runSyncForObject($company, $modelClass, $strategy, $driver, $dryRun, $output);
            }
        }

        // update last reindexed timestamp
        $company->search_last_reindexed = time();
        $company->save();

        // show elapsed time
        $elapsed = round(microtime(true) - $start, 3);
        if ($output && !$dryRun) {
            $output->writeln("Synced company # {$company->id()} in $elapsed seconds");
        }

        if ($output) {
            $peak = round(memory_get_peak_usage() / 1024 / 1024).'MB';
            $current = round(memory_get_usage() / 1024 / 1024).'MB';
            $output->writeln("Memory usage - Peak: $peak, Current: $current");
            $output->writeln('======');
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();
    }

    /**
     * Checks if the index needs to be synced.
     */
    private function shouldRunSync(Company $company, string $modelClass, DriverInterface $driver): bool
    {
        // the sync can be skipped if these conditions are met:
        // 1) the index does not exist
        // 2) there are no records for the model
        /** @var Model $modelClass */
        if (0 == $modelClass::count()) {
            return $driver->getIndex($company, $modelClass)->exists();
        }

        return true;
    }

    /**
     * Reindexes a given model and company.
     *
     * @param bool $dryRun does not actually write the changes
     */
    private function runSyncForObject(Company $company, string $modelClass, ReindexingStrategyInterface $strategy, DriverInterface $driver, bool $dryRun = false, OutputInterface $output = null): void
    {
        // create the index
        $index = $driver->createIndex($company, $modelClass, null, $dryRun, $output);

        // then sync data using the given strategy
        if ($output) {
            $strategyName = explode('\\', $strategy::class);
            $strategyName = end($strategyName);
            $output->writeln("Syncying data for {$index->getName()} using $strategyName strategy...");
        }

        $start = microtime(true);

        $strategy->run($company, $modelClass, $index, $dryRun, $output);

        if ($output) {
            $elapsed = round(microtime(true) - $start, 1);
            $output->writeln("Finished in $elapsed seconds");
        }
    }
}
