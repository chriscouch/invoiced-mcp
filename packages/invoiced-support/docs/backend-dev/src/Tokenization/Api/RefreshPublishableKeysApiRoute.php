<?php

namespace App\Tokenization\Api;

use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Tokenization\Models\PublishableKey;

class RefreshPublishableKeysApiRoute extends AbstractModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: PublishableKey::class,
            features: ['api'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->setModelId($context->request->attributes->get('id'));

        /** @var PublishableKey $publishableKey */
        $publishableKey = $this->retrieveModel($context);

        if ($publishableKey->tenant_id != $this->tenant->get()->id) {
            throw $this->modelNotFoundError();
        }

        $publishableKey->setSecret();
        $publishableKey->save();

        return $publishableKey;
    }
}