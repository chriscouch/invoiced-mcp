<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Normalizers\ModelApiNormalizer;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractModelApiRoute<Charge>
 */
class ListBatchChargesRoute extends AbstractModelApiRoute
{
    public function __construct(
        private readonly ModelApiNormalizer $normalizer,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Charge::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $charges = CustomerPaymentBatch::getEligibleCharges();
        $result = [];
        foreach ($charges as $charge) {
            $chargeArray = $this->normalizer->normalize($charge);
            $result[] = $chargeArray;
        }

        return new JsonResponse($result);
    }
}
