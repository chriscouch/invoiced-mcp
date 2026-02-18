<?php

namespace App\Reports\Api;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Reports\Dashboard\ActivityChart;

class ActivityChartRoute extends AbstractApiRoute
{
    public function __construct(
        private ActivityChart $activityChart,
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

    public function buildResponse(ApiCallContext $context): mixed
    {
        $startDate = (int) $context->request->query->get('start');
        $endDate = (int) $context->request->query->get('end');

        $customer = null;
        if ($cid = $context->request->query->get('customer')) {
            $customer = Customer::find($cid);
        }

        $currency1 = null;
        if ($currency = $context->request->query->get('currency')) {
            $currency1 = $currency;
        }

        $tenant = $this->tenant->get();
        $this->activityChart->setCompany($tenant);

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $this->activityChart->setMember($requester);
        }

        try {
            return $this->activityChart->generate($currency1, $startDate, $endDate, $customer);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
