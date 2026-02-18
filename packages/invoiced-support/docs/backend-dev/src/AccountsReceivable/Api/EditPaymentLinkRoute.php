<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Operations\EditPaymentLink;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractRetrieveModelApiRoute<PaymentLink>
 */
class EditPaymentLinkRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private EditPaymentLink $operation)
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
        $paymentLink = parent::buildResponse($context);

        try {
            $this->operation->edit($paymentLink, $context->requestParameters);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $paymentLink;
    }
}
