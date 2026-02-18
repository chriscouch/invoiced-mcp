<?php

namespace App\PaymentProcessing\Api\Disputes;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Operations\DisputeOperations;

class DisputeReasonsRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private readonly DisputeOperations $disputeOperations,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['payments.edit'],
            modelClass: Dispute::class,
            features: ['accounts_receivable'],
        );
    }

    /**
     * @return array
     */
    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Dispute $dispute */
        $dispute = parent::buildResponse($context);

        return $this->disputeOperations->getCodes($dispute);
    }
}
