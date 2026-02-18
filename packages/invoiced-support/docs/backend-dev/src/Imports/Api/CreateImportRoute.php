<?php

namespace App\Imports\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\EntryPoint\QueueJob\ImportJob;
use App\Imports\Models\Import;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class CreateImportRoute extends AbstractModelApiRoute
{
    public function __construct(private ImportJob $job)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['imports.create'],
            modelClass: Import::class,
        );
    }

    public function buildResponse(ApiCallContext $context): Import
    {
        $type1 = '';
        if ($type = (string) $context->request->request->get('type')) {
            $type1 = $type;
        }

        $mapping1 = [];
        if ($mapping = $context->request->request->all('mapping')) {
            $mapping1 = $mapping;
        }

        $lines1 = [];
        if ($lines = $context->request->request->all('lines')) {
            $lines1 = $lines;
        }

        $options1 = [];
        if ($options = $context->request->request->all('options')) {
            $options1 = $options;
        }

        try {
            return $this->job->create($type1, $mapping1, $lines1, $options1);
        } catch (InvalidArgumentException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
