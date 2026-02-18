<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ECheck;
use App\AccountsPayable\Operations\CreateECheck;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Response;

class ResendECheckApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private readonly CreateECheck $createECheck)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['vendor_payments.create'],
            modelClass: ECheck::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $model = parent::buildResponse($context);

        if (CarbonImmutable::createFromTimestamp($model->created_at)->diffInDays() > 90) {
            throw new InvalidRequest("You can't resend checks written more that 90 days ago");
        }

        $this->createECheck->queueToSend($model);

        return new Response();
    }
}
