<?php

namespace App\AccountsReceivable\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Exception\ModelException;

abstract class VoidDocumentRoute extends AbstractRetrieveModelApiRoute
{
    public function buildResponse(ApiCallContext $context): mixed
    {
        $document = parent::buildResponse($context);

        try {
            $document->void();
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $document;
    }
}
