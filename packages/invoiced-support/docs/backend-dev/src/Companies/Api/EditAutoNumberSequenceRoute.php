<?php

namespace App\Companies\Api;

use App\Companies\Models\AutoNumberSequence;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

class EditAutoNumberSequenceRoute extends AbstractEditModelApiRoute
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
            modelClass: AutoNumberSequence::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // add the company ID to the sequence ID
        $id = $context->request->attributes->get('model_id');
        $tenant = $this->tenant->get();
        $this->setModelIds([$tenant->id(), $id]);

        return parent::buildResponse($context);
    }
}
