<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Operations\CreatePaymentLink;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractModelApiRoute<PaymentLink>
 */
class CreatePaymentLinkRoute extends AbstractModelApiRoute
{
    public function __construct(private CreatePaymentLink $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'customer' => new RequestParameter(),
                'currency' => new RequestParameter(),
                'reusable' => new RequestParameter(),
                'collect_billing_address' => new RequestParameter(),
                'collect_shipping_address' => new RequestParameter(),
                'collect_phone_number' => new RequestParameter(),
                'terms_of_service_url' => new RequestParameter(),
                'after_completion_url' => new RequestParameter(),
                'items' => new RequestParameter(),
                'fields' => new RequestParameter(),
            ],
            requiredPermissions: ['catalog.edit'],
            modelClass: PaymentLink::class,
            features: ['payment_links'],
        );
    }

    public function buildResponse(ApiCallContext $context): PaymentLink
    {
        try {
            return $this->operation->create($context->requestParameters);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
