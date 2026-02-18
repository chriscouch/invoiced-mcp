<?php

namespace App\Reports\Dashboard;

use App\Companies\Models\Member;
use App\Reports\Interfaces\DashboardMetricInterface;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * This class is responsible for caching dashboard metrics which are
 * often expensive to generate and do not need to be calculated on every load.
 */
class DashboardCacheLayer
{
    public function __construct(
        private CacheInterface $cache,
        private Connection $database,
    ) {
    }

    /**
     * Builds a metric and caches it. The result is cached
     * based on the context, metric cache key, and last event.
    //
     */
    public function buildMetric(DashboardMetricInterface $metric, DashboardContext $context, array $options): array
    {
        // Build a cache key based on the context, metric, and options requested.
        $key = $this->getCacheKeyPrefix($context).'_'.$metric::getName().'_'.md5((string) json_encode($options));

        // Some metrics include the last event ID in the cache key.
        // This means once there is a new event in the activity log
        // it will invalidate the cached value.
        if ($metric->invalidateCacheAfterEvent()) {
            $key .= '_'.$this->getLastEventId($context);
        } else {
            $key .= '_v2';
        }

        return $this->cache->get($key, function (ItemInterface $item) use ($metric, $context, $options) {
            $result = $metric->build($context, $options);
            $result['generated_at'] = CarbonImmutable::now()->toIso8601String();

            // This should be set after build() because that will set the company time zone.
            $item->expiresAt($metric->getExpiresAt());

            return $result;
        });
    }

    /**
     * Gets a cache key prefix given a context.
     */
    private function getCacheKeyPrefix(DashboardContext $context): string
    {
        $elements = [$context->company->id];

        if ($context->member && Member::UNRESTRICTED != $context->member->restriction_mode) {
            // When the member is restricted by custom field then
            // use the restrictions as the cache key to maximize
            // cache hits for multiple users with the same restrictions.
            if (Member::CUSTOM_FIELD_RESTRICTION == $context->member->restriction_mode) {
                $elements[] = md5((string) json_encode($context->member->restrictions()));
            } else {
                $elements[] = $context->member->id;
            }
        }

        if ($context->customer) {
            $elements[] = $context->customer->id;
        }

        return implode('_', array_filter($elements));
    }

    private function getLastEventId(DashboardContext $context): int
    {
        // NOTE for now this looks for the account's global last event
        // and does not take into account only the customer's events,
        // if chosen
        return $this->database->createQueryBuilder()
            ->select('id')
            ->from('Events')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $context->company->id())
            ->orderBy('id', 'DESC')
            ->setMaxResults(1)
            ->fetchOne();
    }
}
