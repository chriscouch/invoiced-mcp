<?php

namespace App\AccountsReceivable\ListQueryBuilders;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\ListQueryBuilders\AbstractListQueryBuilder;

/**
 * @extends AbstractListQueryBuilder<T>
 *
 * @template T of ReceivableDocument
 */
abstract class DocumentListQueryBuilder extends AbstractListQueryBuilder
{
    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        $filter = parent::fixLegacyOptions($filter);

        return $this->fixLegacyNumericJson($filter, 'total');
    }

    public function initialize(): void
    {
        // build the query
        $class = $this->getQueryClass();
        $this->query = $class::queryWithTenant($this->company)
            ->with('customer');

        $this->addFilters();
    }
}
