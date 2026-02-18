<?php

namespace App\Core\Search\Driver\Elasticsearch;

use App\Companies\Models\Company;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Remove unused Elasticsearch logical indexes.
 */
class ElasticsearchIndexCleaner
{
    public function __construct(private ElasticsearchDriver $driver)
    {
    }

    /**
     * Deletes orphaned search indexes. An index is considered orphaned
     * when the account whose data it holds has been deleted.
     */
    public function deleteOrphaned(bool $dryRun = false, OutputInterface $output = null): void
    {
        if (0 == count($this->driver->getHosts())) {
            if ($output) {
                $output->writeln('Elasticsearch is disabled');
            }

            return;
        }

        $tenants = $this->driver->getTenants();
        $tenantsToDelete = [];
        foreach ($tenants as $tenantId) {
            if (!Company::find($tenantId)) {
                $tenantsToDelete[] = $tenantId;
            }
        }

        $n = count($tenantsToDelete);
        if ($dryRun) {
            if ($output) {
                $output->writeln("Would delete $n tenants...");
            }
        } else {
            if ($output) {
                $output->writeln("Deleting $n tenants...");
            }

            $this->driver->removeTenants($tenantsToDelete);
        }
    }
}
