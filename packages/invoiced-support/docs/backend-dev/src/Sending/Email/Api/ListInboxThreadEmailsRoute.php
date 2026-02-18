<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Query;

class ListInboxThreadEmailsRoute extends AbstractListEmailsRoute
{
    private int $parentId;

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->where('thread_id', $this->parentId)
            ->sort('id ASC');

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->parentId = (int) $context->request->attributes->get('parent_id');

        return parent::buildResponse($context);
    }
}
