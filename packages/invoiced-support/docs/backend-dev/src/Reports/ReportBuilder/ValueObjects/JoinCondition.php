<?php

namespace App\Reports\ReportBuilder\ValueObjects;

final class JoinCondition
{
    public readonly string $joinType;
    public readonly string $parentColumn;
    public readonly string $joinColumn;
    public readonly ?string $parentTypeColumn;
    public readonly ?string $joinTypeColumn;
    public readonly ?string $joinThroughTable;
    public readonly ?string $joinThroughColumn;

    public function __construct(
        public readonly Table $parentTable,
        public readonly Table $joinTable,
        array $parameters = []
    ) {
        $this->joinType = $parameters['join_type'] ?? 'LEFT JOIN';
        $this->parentColumn = $parameters['parent_column'] ?? $this->joinTable->object.'_id';
        $this->joinColumn = $parameters['join_column'] ?? 'id';
        $this->parentTypeColumn = $parameters['parent_type_column'] ?? null;
        $this->joinTypeColumn = $parameters['join_type_column'] ?? null;
        $this->joinThroughTable = $parameters['join_through_tablename'] ?? null;
        $this->joinThroughColumn = $parameters['join_through_column'] ?? null;
    }
}
