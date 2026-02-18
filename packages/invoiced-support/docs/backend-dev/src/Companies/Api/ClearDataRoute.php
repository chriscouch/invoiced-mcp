<?php

namespace App\Companies\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\CompanyResetJob;
use Symfony\Component\HttpFoundation\Response;

class ClearDataRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Queue $queue,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'settings' => new RequestParameter(
                    default: false,
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: Company::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): Company
    {
        $company = parent::retrieveModel($context);

        // Validate tenant ID matches context
        if ($this->tenant->get()->id != $company->id) {
            throw $this->modelNotFoundError();
        }

        return $company;
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        /** @var Company $company */
        $company = parent::buildResponse($context);

        $this->queue->enqueue(CompanyResetJob::class, [
            'settings' => $context->requestParameters['settings'],
            'tenant_id' => $company->id,
        ], QueueServiceLevel::Batch);

        return new Response('', 204);
    }
}
