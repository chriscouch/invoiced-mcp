<?php

namespace App\Core\Entitlements\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;

class EditFeatureRoute extends AbstractModelApiRoute
{
    public static array $allowed = [
        'estimates',
        'multi_currency',
        'subscriptions',
        'notifications_v2_individual',
    ];

    public function __construct(private TenantContext $tenant)
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

    public function buildResponse(ApiCallContext $context): array
    {
        $feature = (string) $context->request->attributes->get('id');
        $enabled = (bool) $context->request->request->get('enabled');

        if (!$this->model) {
            $this->model = $this->tenant->get();
        }

        if (!in_array($feature, self::$allowed)) {
            throw new InvalidRequest('Invalid feature flag: '.$feature);
        }

        // set the feature flag
        try {
            if ($enabled) {
                $this->model->features->enable($feature);
            } else {
                $this->model->features->disable($feature);
            }
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return [
            'id' => $feature,
            'enabled' => $enabled,
        ];
    }
}
