<?php

namespace App\Exports\Api;

use App\Core\Queue\Queue;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\EntryPoint\QueueJob\MemberACLExportJob;
use App\Exports\Models\Export;
use Symfony\Component\HttpFoundation\Response;

class CreateExportRoute extends AbstractModelApiRoute
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Export::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $type1 = '';
        if ($type = (string) $context->request->request->get('type')) {
            $type1 = $type;
        }

        $options1 = [];
        if ($options = $context->request->request->all('options')) {
            $options1 = $options;
        }

        try {
            return MemberACLExportJob::create($this->queue, $type1, null, $options1);
        } catch (\Exception) {
            throw new ApiError('There was an error creating the export.');
        }
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
