<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\RemittanceAdvice;
use App\CashApplication\Operations\PostRemittanceAdvicePayment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

class PostRemittanceAdvicePaymentRoute extends AbstractModelApiRoute
{
    public function __construct(private PostRemittanceAdvicePayment $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['payments.create'],
            features: ['cash_application'],
        );
    }

    public function buildResponse(ApiCallContext $context): Payment
    {
        $adviceId = (int) $context->request->attributes->get('model_id');
        $advice = $this->getModelOrFail(RemittanceAdvice::class, $adviceId);

        try {
            return $this->operation->post($advice);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
