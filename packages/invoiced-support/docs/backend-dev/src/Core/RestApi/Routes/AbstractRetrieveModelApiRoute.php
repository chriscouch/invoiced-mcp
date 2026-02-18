<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\ValueObjects\ApiCallContext;

/**
 * @template T
 *
 * @extends AbstractModelApiRoute<T>
 */
abstract class AbstractRetrieveModelApiRoute extends AbstractModelApiRoute
{
    /**
     * @return T
     */
    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        return $this->retrieveModel($context);
    }
}
