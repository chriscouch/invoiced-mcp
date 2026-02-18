<?php

namespace App\Integrations\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\ReadSync\AbstractReader;
use App\Integrations\AccountingSync\ValueObjects\AccountingObjectReference;
use App\Integrations\Enums\IntegrationType;

class CreateReconciliationErrorRoute extends AbstractApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'accounting_id' => new RequestParameter(
                    required: true,
                    types: ['numeric'],
                ),
                'integration_id' => new RequestParameter(
                    required: true,
                    types: ['numeric'],
                ),
                'message' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'object' => new RequestParameter(
                    required: true,
                    allowedValues: [ObjectType::CreditNote->typeName(), ObjectType::Customer->typeName(), ObjectType::Invoice->typeName(), ObjectType::Payment->typeName()],
                ),
            ],
            requiredPermissions: [],
        );
    }

    /**
     * @throws SyncException
     */
    public function buildResponse(ApiCallContext $context): ReconciliationError
    {
        $objectType = ObjectType::fromTypeName($context->requestParameters['object']);
        $class = AbstractReader::objectNameToMappingClass($objectType);
        $object = $class::where('accounting_id', $context->requestParameters['accounting_id'])
            ->where('integration_id', $context->requestParameters['integration_id'])
            ->oneOrNull();

        $objectId = $object?->id();
        $objectId = $objectId ? (string) $objectId : null;
        $integrationType = IntegrationType::from($context->requestParameters['integration_id']);

        return ReconciliationError::makeReadError(
            '',
            new AccountingObjectReference($integrationType, $context->requestParameters['object'], $context->requestParameters['accounting_id'], $objectId),
            $context->requestParameters['message']
        );
    }
}
