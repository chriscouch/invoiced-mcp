<?php

namespace App\Integrations\Api;

use App\Core\Files\Models\File;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\Textract\Libs\ExpenseAnalyzer;
use App\Integrations\Textract\Models\TextractImport;

class ImportExpenseDocumentRoute extends AbstractApiRoute
{
    public function __construct(
        private readonly ExpenseAnalyzer $analyzer,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'file_id' => new RequestParameter(
                    required: true,
                    types: ['numeric']
                ),
                'vendor_id' => new RequestParameter(
                    types: ['numeric', 'null'],
                    default: null,
                ),
            ],
            requiredPermissions: ['bills.create'],
        );
    }

    public function buildResponse(ApiCallContext $context): TextractImport
    {
        $file = File::findOrFail($context->requestParameters['file_id']);

        $jobId = $this->analyzer->send($file);

        $import = new TextractImport();
        $import->job_id = $jobId;
        $import->file = $file;
        $import->vendor_id = $context->requestParameters['vendor_id'];
        $import->saveOrFail();

        return $import;
    }
}
