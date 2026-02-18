<?php

namespace App\Core\Search\Driver\Elasticsearch;

use App\Companies\Models\Company;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Elasticsearch\Common\Exceptions\Conflict409Exception;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SplFixedArray;
use SplMinHeap;
use stdClass;
use Throwable;

class ElasticsearchIndex implements IndexInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const TENANTS_INDEX = 'tenants';

    const MAX_SAVE_QUEUE_SIZE = 999;
    const MAX_DELETE_QUEUE_SIZE = 1000;
    private array $saveQueue = [];
    private array $saveQueueTimings = [];
    private array $deleteQueue = [];
    private array $deleteQueueTimings = [];
    private string $name;

    public function __construct(private Company $company, private string $modelClass, private ElasticsearchDriver $driver)
    {
        $this->name = $this->driver->getIndexName($this->modelClass);
    }

    public function __destruct()
    {
        $this->flushSpool();
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function getRoutingKey(): string
    {
        return $this->driver->getRoutingKey($this->company);
    }

    public function insertDocument(string $id, array $document, array $parameters = []): void
    {
        $this->enqueueSave($id, $document);
    }

    public function updateDocument(string $id, array $document, array $parameters = []): void
    {
        $this->enqueueSave($id, $document);
    }

    public function deleteDocument(string $id): void
    {
        $this->enqueueDelete($id);
    }

    public function clearSpool(): void
    {
        $this->saveQueue = [];
        $this->saveQueueTimings = [];
        $this->deleteQueue = [];
        $this->deleteQueueTimings = [];
    }

    public function exists(): bool
    {
        // In Elasticsearch we have indexes that cover
        // multiple tenants. In order to determine existence
        // of the index for a tenant / object type we
        // should get the number of objects in the index
        // and if there is at least one we can say it exists.
        $params = [
            'index' => $this->getName(),
        ];

        try {
            return $this->driver->getClient()->indices()->exists($params);
        } catch (Throwable $e) {
            $this->logger->error('Unable to check if Elasticsearch index "'.$params['index'].'" exists.', ['exception' => $e]);

            return false;
        }
    }

    public function rename(string $newName): void
    {
        // The Elasticsearch integration does not support renaming indexes
        throw new LogicException('Not supported');
    }

    public function delete(): void
    {
        // In Elasticsearch we have physical indexes that span
        // multiple tenants. The physical index SHOULD NOT be
        // deleted. Instead we can remove the records that belong
        // to the tenant from the index.
        $params = [
            'index' => $this->getName(),
            'routing' => $this->getRoutingKey(),
            'body' => [
                'query' => [
                    'match' => [
                        '_tenantId' => $this->company->id(),
                    ],
                ],
            ],
        ];

        try {
            $this->driver->getClient()->deleteByQuery($params);
        } catch (Throwable $e) {
            if (!$e instanceof Conflict409Exception) {
                $this->logger->error('Unable to delete Elasticsearch index "'.$params['index'].'".', ['exception' => $e]);
            }
        }
    }

    public function getIds(): SplFixedArray
    {
        $params = [
            'index' => $this->getName(),
            'routing' => $this->getRoutingKey(),
            'scroll' => '10s', // maximum time between scroll requests
            'size' => 1000,
            'body' => [
                '_source' => false,
                'query' => [
                    'bool' => [
                        'must' => [
                            'match_all' => new stdClass(),
                        ],
                        'filter' => [
                            'term' => ['_tenantId' => $this->company->id()],
                        ],
                    ],
                ],
            ],
        ];

        // Get the IDs from Elasticsearch of every object in the index.
        // Use heap sort since a list of sorted IDs is expected.
        $heap = new SplMinHeap();

        // Execute the search
        // The response will contain the first batch of documents
        // and a scroll_id
        $client = $this->driver->getClient();
        $response = $client->search($params);

        // Now we loop until the scroll "cursors" are exhausted
        $scrollId = null;
        while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
            foreach ($response['hits']['hits'] as $hit) {
                $heap->insert($hit['_id']);
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

        // Build an array from the heap which
        // will produce a sorted list of IDs
        $ids = new SplFixedArray(count($heap));
        $i = 0;
        foreach ($heap as $id) {
            $ids[$i] = $id;
            ++$i;
        }

        return $ids;
    }

    /**
     * Adds a document to the queue to be saved.
     */
    private function enqueueSave(string $id, array $document): void
    {
        $this->saveQueue[$id] = ElasticsearchTransformation::intoIndex($this->company, $document);
        $this->saveQueueTimings[$id] = microtime(true);

        if (count($this->saveQueue) >= self::MAX_SAVE_QUEUE_SIZE) {
            $this->flushSave();
        }
    }

    /**
     * Adds a document to the queue to be deleted.
     */
    private function enqueueDelete(string $id): void
    {
        $this->deleteQueue[] = $id;

        if (count($this->deleteQueue) >= self::MAX_DELETE_QUEUE_SIZE) {
            $this->flushDelete();
        }
    }

    public function flushSpool(): void
    {
        $this->flushSave();
        $this->flushDelete();
    }

    /**
     * Saves all of the documents in the queue.
     */
    private function flushSave(): void
    {
        if (0 === count($this->saveQueue)) {
            return;
        }

        $lines = [];
        foreach ($this->saveQueue as $id => $document) {
            $lines[] = [
                'index' => [
                    '_index' => $this->getName(),
                    '_id' => $id,
                ],
            ];
            $lines[] = $document;
        }

        // ensure this tenant is represented in the indexed tenant list
        $lines[] = [
            'index' => [
                '_index' => self::TENANTS_INDEX,
                '_id' => $this->company->id(),
            ],
        ];
        $lines[] = ['last_indexed' => time()];

        $params = [
            'routing' => $this->getRoutingKey(),
            '_source' => false,
            'body' => $lines,
        ];

        try {
            $this->driver->getClient()->bulk($params);
            $this->statsd->updateStats('search.write_object', count($this->saveQueue), 1.0, ['search_driver' => 'elasticsearch']);
        } catch (Throwable $e) {
            if (!$e instanceof Conflict409Exception) {
                $this->logger->error('Unable to flush Elasticsearch write queue.', ['exception' => $e]);
            }
        }

        foreach ($this->saveQueueTimings as $timing) {
            $time = round((microtime(true) - $timing) * 1000);
            $this->statsd->timing('search.flush_time', $time, 1.0, ['search_driver' => 'elasticsearch']);
        }

        $this->saveQueue = [];
        $this->saveQueueTimings = [];
    }

    /**
     * Deletes all of the deleted documents in the queue.
     */
    private function flushDelete(): void
    {
        if (0 === count($this->deleteQueue)) {
            return;
        }

        $params = [
            'index' => $this->getName(),
            'routing' => $this->getRoutingKey(),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'ids' => [
                                'values' => $this->deleteQueue,
                            ],
                        ],
                        'filter' => [
                            'term' => ['_tenantId' => $this->company->id()],
                        ],
                    ],
                ],
            ],
        ];

        // When deleting a customer the database foreign keys will remove
        // all related records. We want to mirror this in the
        // search index by removing any customer related records
        // in other indexes.
        $customerParams = null;
        if ('customers' == $params['index']) {
            $customerParams = [
                'index' => 'contact,credit_note,estimate,invoice,payment,subscription',
                'routing' => $this->getRoutingKey(),
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                'terms' => [
                                    '_customer' => $this->deleteQueue,
                                ],
                            ],
                            'filter' => [
                                'term' => ['_tenantId' => $this->company->id()],
                            ],
                        ],
                    ],
                ],
            ];
        }

        try {
            $client = $this->driver->getClient();
            $client->deleteByQuery($params);

            // delete related customer records
            if ($customerParams) {
                $client->deleteByQuery($customerParams);
            }

            $this->statsd->updateStats('search.delete_object', count($this->deleteQueue), 1.0, ['search_driver' => 'elasticsearch']);
        } catch (Throwable $e) {
            if (!$e instanceof Conflict409Exception) {
                $this->logger->error('Unable to flush delete queue for Elasticsearch index "'.$params['index'].'".', ['exception' => $e]);
            }
        }

        foreach ($this->deleteQueueTimings as $timing) {
            $time = round((microtime(true) - $timing) * 1000);
            $this->statsd->timing('search.flush_time', $time, 1.0, ['search_driver' => 'elasticsearch']);
        }

        $this->deleteQueue = [];
        $this->deleteQueueTimings = [];
    }
}
