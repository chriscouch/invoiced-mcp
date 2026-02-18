<?php

namespace App\Integrations\Zapier\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Webhooks\Models\Webhook;

class UnsubscribeRoute extends AbstractModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Webhook::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $targetUrl = $context->request->request->get('target_url');

        $model = $this->model;
        $webhooks = $model::where('url', $targetUrl)->all();

        foreach ($webhooks as $webhook) {
            if (!$webhook->delete()) {
                // get the first error
                if ($error = $this->getFirstError()) {
                    throw $this->modelValidationError($error);
                }

                throw new ApiError('Could not delete webhook');
            }
        }

        return [
            'url' => $targetUrl,
            'unsubscribed' => true,
        ];
    }
}
