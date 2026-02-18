<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Exception\MassAssignmentException;

/**
 * @template T
 *
 * @extends AbstractModelApiRoute<T>
 */
abstract class AbstractEditModelApiRoute extends AbstractModelApiRoute
{
    /**
     * @return T
     */
    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $model = $this->retrieveModel($context);

        $parameters = $this->hydrateRelationships($context->requestParameters);

        try {
            if ($model->set($parameters)) {
                return $model;
            }
        } catch (MassAssignmentException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic one
        throw new ApiError('There was an error updating the '.$this->getModelName().'.');
    }
}
