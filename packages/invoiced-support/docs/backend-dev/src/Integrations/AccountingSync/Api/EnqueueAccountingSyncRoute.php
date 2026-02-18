<?php

namespace App\Integrations\AccountingSync\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Integrations\AccountingSync\ReadSync\ReadSyncJobClassFactory;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enqueues an integration read sync.
 */
class EnqueueAccountingSyncRoute extends AbstractApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private Queue $queue,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'historical_sync' => new RequestParameter(
                    types: ['boolean'],
                    default: false,
                ),
                'readers' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'start_date' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'end_date' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'open_items_only' => new RequestParameter(
                    types: ['boolean', 'null'],
                ),
            ],
            requiredPermissions: [],
            features: ['accounting_sync'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $args = [
            'tenant_id' => $this->tenant->get()->id(),
        ];

        if ($context->requestParameters['historical_sync']) {
            if (!isset($context->requestParameters['start_date'])) {
                throw new InvalidRequest('Missing start date');
            }
            $startDate = new CarbonImmutable($context->requestParameters['start_date']);

            if (!isset($context->requestParameters['end_date'])) {
                throw new InvalidRequest('Missing end date');
            }
            $endDate = new CarbonImmutable($context->requestParameters['end_date']);

            if ($endDate->isAfter(CarbonImmutable::now()->endOfDay())) {
                throw new InvalidRequest('End date cannot be in the future');
            }

            $diffInSeconds = $endDate->getTimestamp() - $startDate->getTimestamp();
            if ($endDate->isBefore($startDate) || $diffInSeconds > 86400 * 366) { // allow for a leap year
                throw new InvalidRequest('The date range cannot be greater than one year');
            }

            if (!isset($context->requestParameters['open_items_only'])) {
                throw new InvalidRequest('Missing open items only');
            }
            $query = new ReadQuery(
                startDate: $startDate,
                endDate: $endDate,
                openItemsOnly: $context->requestParameters['open_items_only'],
            );
            $args['historical_sync'] = true;
            $args = array_merge($args, $query->jsonSerialize());

            if (!isset($context->requestParameters['readers']) || !$context->requestParameters['readers']) {
                throw new InvalidRequest('You must specify at least one type of data you want to sync');
            }
            $args['readers'] = $context->requestParameters['readers'];
        }

        try {
            $integration = IntegrationType::fromString($context->request->attributes->get('id'));
            $jobClass = ReadSyncJobClassFactory::get($integration);
            $this->queue->enqueue($jobClass, $args, QueueServiceLevel::Batch);
        } catch (IntegrationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return new Response('', 204);
    }
}
