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
class DisputeUploadFilesRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private readonly DisputeOperations $disputeOperations,
        private readonly AdyenDisputeFileValidator $adyenDisputeFileValidator
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
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

        $this->disputeOperations->supplyDocuments($dispute, $context->request->files, $context->requestParameters['codes']);

        return $dispute;
    }
}
