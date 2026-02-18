<?php

namespace App\AccountsReceivable\Api\Coupons;

use App\Core\Orm\Query;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Metadata\Api\ListModelsWithMetadataRoute;

abstract class AbstractListRatesRoute extends ListModelsWithMetadataRoute
{
    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $showArchived = (bool) $context->request->query->get('archived');
        if ($showArchived) {
            $query->where('archived', true);
        } else {
            $query->where('archived', false);
        }

        return $query;
    }
}
