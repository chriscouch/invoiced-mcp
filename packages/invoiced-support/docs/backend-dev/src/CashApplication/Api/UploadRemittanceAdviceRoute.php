<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\RemittanceAdvice;
use App\CashApplication\Operations\UploadRemittanceAdvice;
use App\Core\Files\Models\File;
use App\Core\Orm\Exception\ModelException;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\Exceptions\IntegrationApiException;

class UploadRemittanceAdviceRoute extends AbstractModelApiRoute
{
    public function __construct(private UploadRemittanceAdvice $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'file' => new RequestParameter(required: true),
            ],
            requiredPermissions: ['payments.create'],
            features: ['cash_application'],
        );
    }

    public function buildResponse(ApiCallContext $context): RemittanceAdvice
    {
        $file = $this->getModelOrFail(File::class, $context->requestParameters['file']);

        try {
            return $this->operation->upload($file);
        } catch (ModelException|IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
