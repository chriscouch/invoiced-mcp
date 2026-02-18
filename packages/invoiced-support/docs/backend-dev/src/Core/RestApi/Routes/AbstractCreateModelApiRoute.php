<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Exception\MassAssignmentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template T
 *
 * @extends AbstractModelApiRoute<T>
 */
abstract class AbstractCreateModelApiRoute extends AbstractModelApiRoute
{
    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->model) {
            throw $this->requestNotRecognizedError($context->request);
        }

        $parameters = $this->hydrateRelationships($context->requestParameters);

        try {
            if ($this->model->create($parameters)) {
                return $this->model;
            }
        } catch (MassAssignmentException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a server error
        throw new ApiError('There was an error creating the '.$this->getModelName().'.');
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
