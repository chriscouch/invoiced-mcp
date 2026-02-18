<?php

namespace App\Reports\Api;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Reports\Dashboard\Dashboard;

class DashboardRoute extends AbstractApiRoute
{
    public function __construct(
        private Dashboard $dashboard,
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
        if ($cid = $context->request->query->get('customer')) {
            $this->dashboard->setCustomer(Customer::find($cid));
        }

        if ($currency = $context->request->query->get('currency')) {
            $currency1 = $currency;
        } else {
            $currency1 = $this->tenant->get()->currency;
        }

        $tenant = $this->tenant->get();
        $this->dashboard->setCompany($tenant);

        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $this->dashboard->setMember($requester);
        }

        return $this->dashboard->generate($currency1);
    }
}
