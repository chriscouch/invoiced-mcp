<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Traits\PdfApiTrait;

class RetrieveDocumentRoute extends AbstractRetrieveModelApiRoute
{
    use PdfApiTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var ReceivableDocument $document */
        $document = parent::buildResponse($context);

        // Return PDF if application/pdf is requested
        if ($this->shouldReturnPdf($context->request)) {
            $locale = $document->customer()->getLocale();

            return $this->buildResponsePdf($document, $locale);
        }

        return $document;
    }
}
