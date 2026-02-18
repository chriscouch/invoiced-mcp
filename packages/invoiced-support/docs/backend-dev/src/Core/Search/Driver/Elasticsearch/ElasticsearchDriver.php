<?php

namespace App\Core\Search\Driver\Elasticsearch;

use App\Companies\Models\Company;
use App\Core\Search\Interfaces\DriverInterface;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Libs\IndexRegistry;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use LukeWaite\RingPhpGuzzleHandler\GuzzleHandler;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use stdClass;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ElasticsearchDriver implements DriverInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private Client $client;
    private bool $useQueryStringQuery = true;

    public function __construct(
        private array $hosts,
        private Connection $database,
        private IndexRegistry $registry,
    ) {
    }

    public function getHosts(): array
    {
        return $this->hosts;
    }

    public function getIndex(Company $company, string $modelClass): ElasticsearchIndex
    {
        $index = new ElasticsearchIndex($company, $modelClass, $this);
        $index->setLogger($this->logger);
        $index->setStatsd($this->statsd);

        return $index;
    }

    public function createIndex(Company $company, string $modelClass, ?string $name = null, bool $dryRun = false, ?OutputInterface $output = null): IndexInterface
    {
        $index = $this->getIndex($company, $modelClass);

        try {
            $client = $this->getClient();
            $exists = $client->indices()->exists(['index' => $index->getName()]);
            if (!$exists) {
                $params = [
                    'index' => $index->getName(),
                    'body' => [
                        'settings' => ElasticsearchIndexConfiguration::getSettings($index->getName()),
                    ],
                ];
                if ($mappings = ElasticsearchIndexConfiguration::getMapping($index->getName())) {
                    $params['body']['mappings'] = $mappings;
                }

                $client->indices()->create($params);
            }
        } catch (Throwable $e) {
            $this->logger->error('Unable to create Elasticsearch index "'.$index->getName().'".', ['exception' => $e]);
        }

        return $index;
    }

    public function search(Company $company, string $query, ?string $modelClass, int $numResults): array
    {
        $indexName = null;
        if ($modelClass) {
            $indexName = $this->getIndexName($modelClass);
        }

        $queryBuilder = new ElasticsearchQueryBuilder($this->database, $this->registry);
        $params = $queryBuilder->build($query, $indexName, $company, $numResults, $this->useQueryStringQuery);
        $params['routing'] = $this->getRoutingKey($company);

        try {
            $client = $this->getClient();
            $response = $client->search($params);
        } catch (Throwable $e) {
            // If the search fails due to a 400 invalid request
            // then that likely means that our query string syntax
            // was not valid. When that happens we want to try the
            // search again using a simpler exact match query.
            if ($e instanceof BadRequest400Exception && $this->useQueryStringQuery) {
                $this->useQueryStringQuery = false;

                return $this->search($company, $query, $modelClass, $numResults);
            }

            if (!($e instanceof Missing404Exception) && !str_contains($e->getMessage(), 'too_complex_to_determinize_exception')) {
                $this->logger->error('Unable to search Elasticsearch index "'.$params['index'].'".', ['exception' => $e]);
            }

            return [];
        }

        $this->statsd->timing('search.es_query_time', $response['took']);
        if ($response['timed_out']) {
            $this->statsd->increment('search.es_query_timed_out');
        }

        // If there were no results found using a query string search
        // then try an exact match search.
        if (0 == count($response['hits']['hits']) && $this->useQueryStringQuery) {
            $this->useQueryStringQuery = false;

            return $this->search($company, $query, $modelClass, $numResults);
        }

        $results = [];
        foreach ($response['hits']['hits'] as $hit) {
            $results[] = ElasticsearchTransformation::fromIndex($hit);
        }

        return $results;
    }

    public function getClient(): Client
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $this->client = ClientBuilder::create()
            ->setHandler(new GuzzleHandler())
            ->setHosts($this->hosts)
            ->build();

        return $this->client;
    }

    public function getRoutingKey(Company $company): string
    {
        return (string) $company->id();
    }

    public function updateSettings(string $indexName, OutputInterface $output): void
    {
        try {
            $output->writeln('Checking if index exists');
            $client = $this->getClient();
            $exists = $client->indices()->exists(['index' => $indexName]);
            if (!$exists) {
                $this->updateSettingsNew($client, $indexName, $output);
            } else {
                $this->updateSettingsExisting($client, $indexName, $output);
            }
        } catch (Throwable $e) {
            $this->logger->error('Unable to update settings for Elasticsearch index "'.$indexName.'".', ['exception' => $e]);
        }
    }

    /**
     * Creates a new index and configures it.
     */
    private function updateSettingsNew(Client $client, string $indexName, OutputInterface $output): void
    {
        $output->writeln('Creating new index...');
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => ElasticsearchIndexConfiguration::getSettings($indexName),
            ],
        ];
        if ($mappings = ElasticsearchIndexConfiguration::getMapping($indexName)) {
            $params['body']['mappings'] = $mappings;
        }

        $client->indices()->create($params);
    }

    /**
     * Updates the settings of an existing index.
     *
     * Elasticsearch does not allow changing mapping types.
     * First we will attempt to modify the mapping and if
     * that fails we will have to rebuild the index which
     * is a very slow operation.
     */
    private function updateSettingsExisting(Client $client, string $indexName, OutputInterface $output): void
    {
        $output->writeln('Attempting to update mapping of existing index...');

        try {
            $params = [
                'index' => $indexName,
                'body' => ElasticsearchIndexConfiguration::getMapping($indexName),
            ];
            $client->indices()->putMapping($params);
            $output->writeln('Settings update complete');
        } catch (BadRequest400Exception) {
            $output->writeln('Mapping update failed. Rebuilding index...');
            $this->rebuildIndex($client, $indexName, $output);
        }
    }

    private function rebuildIndex(Client $client, string $indexName, OutputInterface $output): void
    {
        /*
         Steps to rebuild an index with a new mapping:
         1. Create a new index with the correct settings and mapping
         2. Use the Reindex API to copy data from original index to new index
         3. Remove old index
         4. Create an alias to the new index.
         */

        // Step 1 - Create New Index
        $newIndexName = $indexName.'-'.uniqid();
        $output->writeln('Creating new index: '.$newIndexName);
        $this->updateSettings($newIndexName, $output);

        // Step 2 - Reindex
        $output->writeln('Building new index: '.$newIndexName);
        $client->reindex([
            'body' => [
                'source' => [
                    'index' => $indexName,
                ],
                'dest' => [
                    'index' => $newIndexName,
                ],
            ],
        ]);

        // Step 3 - Delete Old Index
        // The old index can be an alias or an index which have
        // different techniques to delete
        try {
            $result = $client->indices()->getAlias(['name' => $indexName]);
            $oldIndexName = array_keys($result)[0];

            $output->writeln('Deleting old alias: '.$indexName);
            $client->indices()->deleteAlias([
                'name' => $indexName,
                'index' => $oldIndexName,
            ]);
        } catch (Missing404Exception) {
            $oldIndexName = $indexName;
        }

        $output->writeln('Deleting old index: '.$oldIndexName);
        $client->indices()->delete(['index' => $oldIndexName]);

        // Step 4 - Create alias
        $output->writeln('Creating alias for '.$indexName.' -> '.$newIndexName);
        $client->indices()->updateAliases([
            'body' => [
                'actions' => [
                    ['add' => ['index' => $newIndexName, 'alias' => $indexName]],
                ],
            ],
        ]);
    }

    /**
     * Gets the list of tenant IDs that have representation in the search index.
     */
    public function getTenants(): array
    {
        $params = [
            'index' => ElasticsearchIndex::TENANTS_INDEX,
            'scroll' => '10s', // maximum time between scroll requests
            'size' => 1000,
            'body' => [
                '_source' => false,
                'query' => [
                    'match_all' => new stdClass(),
                ],
            ],
        ];

        // Execute the search
        // The response will contain the first batch of documents
        // and a scroll_id
        $ids = [];
        $client = $this->getClient();
        $response = $client->search($params);

        // Now we loop until the scroll "cursors" are exhausted
        $scrollId = null;
        while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
            foreach ($response['hits']['hits'] as $hit) {
                $ids[] = $hit['_id'];
            }

            // When done, get the new scroll_id
            // You must always refresh your _scroll_id!  It can change sometimes
            $scrollId = $response['_scroll_id'];

            // Execute a Scroll request and repeat
            $response = $client->scroll([
                'body' => [
                    'scroll_id' => $scrollId,  // ...using our previously obtained _scroll_id
                    'scroll' => '10s',        // and the same timeout window
                ],
            ]);
        }

        // Clean up the search context when finished
        $scrollId = $response['_scroll_id'] ?? $scrollId;
        if ($scrollId) {
            $client->clearScroll(['body' => ['scroll_id' => $scrollId]]);
        }

        return $ids;
    }

    public function removeTenants(array $tenants): void
    {
        foreach ($this->registry->getIndexableObjects() as $modelClass) {
            $indexName = $this->getIndexName($modelClass);
            $params = [
                'index' => $indexName,
                'body' => [
                    'query' => [
                        'ids' => [
                            'values' => $tenants,
                        ],
                    ],
                ],
            ];

            try {
                $this->getClient()->deleteByQuery($params);
            } catch (Throwable $e) {
                if (!$e instanceof Conflict409Exception) {
                    $this->logger->error('Unable to evict tenant data from Elasticsearch index "'.$indexName.'".', ['exception' => $e]);
                }
            }
        }

        $params = [
            'index' => ElasticsearchIndex::TENANTS_INDEX,
            'body' => [
                'query' => [
                    'ids' => [
                        'values' => $tenants,
                    ],
                ],
            ],
        ];

        try {
            $this->getClient()->deleteByQuery($params);
        } catch (Throwable $e) {
            if (!$e instanceof Conflict409Exception) {
                $this->logger->error('Unable to evict tenant from Elasticsearch tenant index.', ['exception' => $e]);
            }
        }
    }

    public function getIndexName(string $modelClass): string
    {
        return ObjectType::fromModelClass($modelClass)->typeName();
    }
}
