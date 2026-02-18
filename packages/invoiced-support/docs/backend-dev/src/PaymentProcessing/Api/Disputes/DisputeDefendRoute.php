<?php

namespace App\PaymentProcessing\Api\Disputes;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\PaymentProcessing\Libs\AdyenDisputeFileValidator;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Operations\DisputeOperations;

/**
 * @extends AbstractRetrieveModelApiRoute<Dispute>
 */
class DisputeDefendRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private readonly DisputeOperations $disputeOperations,
        private readonly AdyenDisputeFileValidator $adyenDisputeFileValidator,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'reason_code' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'codes' => new RequestParameter(
                    types: ['array'],
                ),
            ],
            requiredPermissions: ['payments.edit'],
            modelClass: Dispute::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Dispute $dispute */
        $dispute = parent::buildResponse($context);

        $this->adyenDisputeFileValidator->validateFiles($context->request->files);

        $reasonCode = $context->request->get('reason_code');

        $this->disputeOperations->defend($dispute, $reasonCode, $context->request->files, $context->requestParameters['codes']);

        return $dispute;
    }
}
