<?php

namespace App\Sending\Mail\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Mail\Models\Letter;

class ListLettersByDocumentRoute extends AbstractListModelsApiRoute
{
    private string $document_type;
    private int $document_id;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Letter::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->document_type = (string) $context->request->attributes->get('document_type');
        $this->document_id = (int) $context->request->attributes->get('document_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        return parent::buildQuery($context)
            ->where('related_to_type', ObjectType::fromTypeName($this->document_type)->value)
            ->where('related_to_id', $this->document_id);
    }
}
