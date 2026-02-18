<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentLinkSession;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<PaymentLinkSession>
 */
class ListPaymentLinkSessionsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PaymentLinkSession::class,
            features: ['payment_links'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $paymentLinkId = $context->request->attributes->get('payment_link_id');

        return parent::buildQuery($context)
            ->where('payment_link_id', $paymentLinkId)
            ->where('completed_at', null, '<>');
    }
}
