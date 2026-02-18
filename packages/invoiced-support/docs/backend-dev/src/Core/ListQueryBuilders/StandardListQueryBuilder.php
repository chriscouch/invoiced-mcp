<?php

namespace App\Core\ListQueryBuilders;

use App\Core\Orm\Model;

class StandardListQueryBuilder extends AbstractListQueryBuilder
{
    public function initialize(): void
    {
        // build the query
        $class = $this->getQueryClass();
        $this->query = $class::queryWithTenant($this->company);

        $this->addFilters();
    }

    public static function getClassString(): string
    {
        return Model::class;
    }
}
