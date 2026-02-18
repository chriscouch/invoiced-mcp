<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Query;

class ListInboxThreadsRoute extends ListInboxThreadsAbstractRoute
{
    private int $inboxId;

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);
        $query->where('inbox_id', $this->inboxId);

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->inboxId = (int) $context->request->attributes->get('model_id');

        return parent::buildResponse($context);
    }
}
