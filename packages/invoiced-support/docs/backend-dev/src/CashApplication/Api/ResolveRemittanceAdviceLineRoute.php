<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\RemittanceAdviceLine;
use App\CashApplication\Operations\ResolveRemittanceAdviceLine;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

class ResolveRemittanceAdviceLineRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private ResolveRemittanceAdviceLine $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['payments.create'],
            modelClass: RemittanceAdviceLine::class,
            features: ['cash_application'],
        );
    }

    public function buildResponse(ApiCallContext $context): RemittanceAdviceLine
    {
        $line = parent::buildResponse($context);

        // TODO: can check remittance advice ID matches

        try {
            $this->operation->resolve($line);

            return $line;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
