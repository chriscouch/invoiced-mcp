<?php

namespace App\Chasing\Api;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use Symfony\Component\HttpFoundation\Response;

class MassAssignLateFeeScheduleRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'customers' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
            ],
            requiredPermissions: ['customers.edit'],
            modelClass: LateFeeSchedule::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        /** @var LateFeeSchedule $lateFeeSchedule */
        $lateFeeSchedule = parent::buildResponse($context);

        foreach ($context->requestParameters['customers'] as $customerId) {
            $customer = Customer::find($customerId);
            if (!$customer) {
                throw new InvalidRequest('No such customer: '.$customerId);
            }

            $customer->late_fee_schedule = $lateFeeSchedule;

            $customer->skipReconciliation();
            if (!$customer->save()) {
                throw new InvalidRequest('Could not assign late fee schedule: '.$customer->getErrors());
            }
        }

        return new Response('', 204);
    }
}
