<?php

namespace App\Reports\ReportBuilder\ValueObjects;

final class DataQuery
{
    public function __construct(
        public readonly Table $table,
        public readonly Joins $joins,
        public readonly Fields $fields,
        public readonly Filter $filter,
        public readonly Group $groupBy,
        public readonly Sort $sort,
        public readonly ?int $maxResults = null,
        public readonly bool $withReferenceColumns = true,
    ) {
    }
}
