<?php

namespace App\Integrations\Avalara\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Avalara\AvalaraApi;
use App\Integrations\Exceptions\IntegrationApiException;

class ListAvalaraCompaniesRoute extends AbstractApiRoute
{
    public function __construct(private AvalaraApi $avalaraApi, private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $accountId = $context->request->request->get('account_id');
        $licenseKey = $context->request->request->get('license_key');

        try {
            return $this->avalaraApi->getCompanies((string) $accountId, (string) $licenseKey, $this->tenant->get());
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
