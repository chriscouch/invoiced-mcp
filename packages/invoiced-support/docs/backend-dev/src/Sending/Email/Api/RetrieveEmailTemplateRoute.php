<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Sending\Email\Models\EmailTemplate;

class RetrieveEmailTemplateRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: EmailTemplate::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // add the company ID to the email template ID
        $id = $context->request->attributes->get('model_id');
        $tenant = $this->tenant->get();
        $this->setModelIds([$tenant->id(), $id]);

        return parent::buildResponse($context);
    }
}
