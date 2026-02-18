<?php

namespace App\Core\Search\Libs;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;

/**
 * Resets the search indexes for a company.
 */
class SearchReset
{
    public function __construct(
        private Search $search,
        private TenantContext $tenant,
        private IndexRegistry $registry,
    ) {
    }

    /**
     * Destroys the search indexes for a given account.
     */
    public function run(Company $company): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        // sync a given object
        $driver = $this->search->getDriver($company);
        $objects = $this->registry->getIndexableObjectsForCompany($company);
        foreach ($objects as $modelClass) {
            $index = $driver->getIndex($company, $modelClass);
            if ($index->exists()) {
                $index->delete();
            }
        }

        // update last reindexed timestamp
        $company->search_last_reindexed = null;
        $company->save();
    }
}
