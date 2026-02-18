<?php

namespace App\Core\Search\Libs;

use App\Companies\Models\Company;
use App\Core\Search\Driver\Database\DatabaseDriver;
use App\Core\Search\Driver\Elasticsearch\ElasticsearchDriver;
use App\Core\Search\Interfaces\DriverInterface;
use App\Core\Search\Interfaces\IndexInterface;
use LogicException;

class Search
{
    /** @var IndexInterface[] */
    private array $indexes = [];

    public function __construct(
        private DatabaseDriver $database,
        private ElasticsearchDriver $elasticsearch,
        private string $defaultDriver,
    ) {
    }

    /**
     * Determines the appropriate search backend
     * to use for the company.
     *
     * @param string|null $driver when supplied overrides the company's default search backend
     */
    public function getDriver(Company $company, ?string $driver = null): DriverInterface
    {
        return match ($this->defaultDriver) {
            'database' => $this->database,
            'elasticsearch' => $this->elasticsearch,
            default => throw new LogicException('Search backend not recognized: '.$this->defaultDriver),
        };
    }

    /**
     * Gets an instance of the requested search index.
     *
     * @param string|null $driver when supplied overrides the company's default search backend
     */
    public function getIndex(Company $company, string $modelClass, ?string $driver = null): IndexInterface
    {
        $key = $company->id().'_'.$modelClass;
        if (!isset($this->indexes[$key])) {
            $this->indexes[$key] = $this->getDriver($company, $driver)
                ->getIndex($company, $modelClass);
        }

        return $this->indexes[$key];
    }

    /**
     * This clears all the spooled indexing operations. You would
     * call this if there was a rollback and any spooled indexing operations
     * during the rolled back transaction no longer apply.
     */
    public function clearIndexSpools(): void
    {
        foreach ($this->indexes as $index) {
            $index->clearSpool();
        }
    }

    /**
     * Writes all the spooled indexing operations. You would
     * call this if you want any spooled indexing operations
     * to be performed.
     */
    public function flushIndexSpools(): void
    {
        foreach ($this->indexes as $index) {
            $index->flushSpool();
        }
    }
}
