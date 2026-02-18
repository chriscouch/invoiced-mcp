<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template T
 *
 * @extends AbstractModelApiRoute<T>
 */
abstract class AbstractDeleteModelApiRoute extends AbstractModelApiRoute
{
    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $model = $this->retrieveModel($context);

        if ($model->delete()) {
            return new Response('', 204);
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic error
        throw new ApiError('There was an error deleting the '.$this->getModelName().'.');
    }
}
