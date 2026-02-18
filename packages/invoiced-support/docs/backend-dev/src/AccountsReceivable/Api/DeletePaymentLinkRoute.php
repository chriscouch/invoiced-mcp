<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Operations\DeletePaymentLink;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractRetrieveModelApiRoute<PaymentLink>
 */
class DeletePaymentLinkRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private DeletePaymentLink $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['catalog.edit'],
            modelClass: PaymentLink::class,
            features: ['payment_links'],
        );
    }

    public function buildResponse(ApiCallContext $context): PaymentLink
    {
        $paymentLink = parent::buildResponse($context);

        try {
            $this->operation->delete($paymentLink);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $paymentLink;
    }
}
