<?php

namespace App\Integrations\AccountingSync\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;

/**
 * @extends AbstractListModelsApiRoute<ReconciliationError>
 */
class ListReconciliationErrorsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: ReconciliationError::class,
            filterableProperties: ['object', 'object_id', 'level', 'accounting_id', 'integration_id'],
        );
    }

    protected function parseFilterInput(ApiCallContext $context, array $input): ListFilter
    {
        if (isset($input['integration'])) {
            try {
                $input['integration_id'] = IntegrationType::fromString($input['integration'])->value;
                unset($input['integration']);
            } catch (IntegrationException $e) {
                throw new InvalidRequest($e->getMessage());
            }
        }

        return parent::parseFilterInput($context, $input);
    }
}
