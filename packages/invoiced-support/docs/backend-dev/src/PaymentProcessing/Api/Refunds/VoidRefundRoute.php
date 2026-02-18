<?php

namespace App\PaymentProcessing\Api\Refunds;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Refund;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use Symfony\Component\HttpFoundation\Response;
use App\PaymentProcessing\Operations\VoidRefund;

/**
 * @extends AbstractRetrieveModelApiRoute<Refund>
 */
class VoidRefundRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private VoidRefund $voidRefund)
    {
    }
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Refund::class,
            features: ['accounts_receivable'],
        );
    }
    
    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }
        
        $refund = $this->retrieveModel($context);
        $this->voidRefund->void($refund);
        return $refund->refresh();
    }
    
    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
