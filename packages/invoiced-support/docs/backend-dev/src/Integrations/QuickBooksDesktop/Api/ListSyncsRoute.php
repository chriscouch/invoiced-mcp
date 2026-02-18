<?php

namespace App\Integrations\QuickBooksDesktop\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\QuickBooksDesktop\QuickBooksDesktopSyncManager;

class ListSyncsRoute extends AbstractApiRoute
{
    const MAX_PAST_JOBS = 5;

    public function __construct(
        private TenantContext $tenant,
        private QuickBooksDesktopSyncManager $syncManager,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // fetch the most recent sync jobs
        $activeJobs = [];
        $pastJobs = [];

        $jobs = $this->syncManager->getJobs($this->tenant->get());
        foreach ($jobs as $job) {
            if ($job->active) { /* @phpstan-ignore-line */
                $activeJobs[] = $job->toArray();
            } elseif (count($pastJobs) < self::MAX_PAST_JOBS) {
                $pastJobs[] = $job->toArray();
            }
        }

        return [
            'active_jobs' => $activeJobs,
            'past_jobs' => $pastJobs,
            'available_syncs' => [], // no longer used
            'recent_jobs_by_sync' => [], // no longer used
        ];
    }
}
