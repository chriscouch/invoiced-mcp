<?php

namespace App\Reports\Api;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Reports\Dashboard\DashboardCacheLayer;
use App\Reports\Interfaces\DashboardMetricInterface;
use App\Reports\ValueObjects\DashboardContext;
use Symfony\Component\DependencyInjection\ServiceLocator;

class DashboardMetricRoute extends AbstractApiRoute
{
    public function __construct(
        private ServiceLocator $metricLocator,
        private DashboardCacheLayer $cacheLayer,
        private TenantContext $tenant,
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

    public function buildResponse(ApiCallContext $context): array
    {
        $metricName = $context->request->attributes->get('metric');
        if (!$this->metricLocator->has($metricName)) {
            throw $this->requestNotRecognizedError($context->request);
        }

        $company = $this->tenant->get();
        $member = null;
        $customer = null;

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $member = $requester;
        }

        if (isset($context->queryParameters['customer'])) {
            $customer = Customer::findOrFail($context->queryParameters['customer']);
        }

        $dashboardContext = new DashboardContext($company, $member, $customer);

        /** @var DashboardMetricInterface $metric */
        $metric = $this->metricLocator->get($metricName);

        return $this->cacheLayer->buildMetric($metric, $dashboardContext, $context->queryParameters);
    }
}
