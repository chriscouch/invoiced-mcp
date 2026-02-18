<?php

namespace App\Integrations\AccountingSync\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryReconciliationError;
use App\Integrations\Exceptions\IntegrationException;

/**
 * @extends AbstractRetrieveModelApiRoute<ReconciliationError>
 */
class RetryReconciliationRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private RetryReconciliationError $retry)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: ReconciliationError::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $error = parent::buildResponse($context);

        try {
            $this->retry->retry($error);
        } catch (IntegrationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $error;
    }
}
