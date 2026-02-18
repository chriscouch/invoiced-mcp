<?php

namespace App\Core\RestApi\Libs;

use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\SimpleCache;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ApiCache implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private CacheInterface $cache,
        private SimpleCache $psr16Cache,
    ) {
    }

    /**
     * Gets the total number of records for an ORM query.
     * The value returned will be cached for 5 minutes.
     * Use this function wisely because it is
     * likely that the cached count becomes stale.
     */
    public function getCachedQueryCount(Query $query, bool $recompute): int
    {
        $hashKey = $this->getQueryHashKey($query, null);
        // INF forces an immediate recompute, even if cached
        $beta = $recompute ? INF : null;

        return $this->cache->get($hashKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(300); // 5 minutes

            return $query->count();
        }, $beta);
    }

    public function getPaginationCursor(Query $query, int $offset): ?string
    {
        $hashKey = 'pagination_cursor_'.$this->getQueryHashKey($query, $offset);
        $cursor = $this->psr16Cache->get($hashKey);
        if ($cursor && isset($this->statsd)) {
            $this->statsd->increment('api.cursor_pagination');
        }

        return $cursor;
    }

    public function storePaginationCursor(Query $query, int $offset, string $cursor): void
    {
        $hashKey = 'pagination_cursor_'.$this->getQueryHashKey($query, $offset);
        $this->psr16Cache->set($hashKey, $cursor, 300); // 5 minutes
    }

    private function getQueryHashKey(Query $query, ?int $offset): string
    {
        $model = $query->getModel();
        $modelClass = $model instanceof Model ? get_class($model) : $model;
        $hashKey = ['model:'.$modelClass];

        foreach ($query->getJoins() as $join) {
            $hashKey[] = 'join:'.implode(',', $join);
        }

        foreach ($query->getWhere() as $key => $value) {
            if (!is_numeric($key)) {
                $where = [$key, $this->convertValueToString($value)];
            } elseif (is_array($value)) {
                $where = [];
                foreach ($value as $el) {
                    $where[] = $this->convertValueToString($el);
                }
            } else {
                $where = [$this->convertValueToString($value)];
            }
            $hashKey[] = 'where:'.implode(',', $where);
        }

        if ($offset) {
            $hashKey[] = 'offset:'.$offset;
        }

        sort($hashKey);

        return md5(implode('|', $hashKey));
    }

    private function convertValueToString(mixed $value): string
    {
        if ($value instanceof Model) {
            return (string) $value->id();
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }
}
