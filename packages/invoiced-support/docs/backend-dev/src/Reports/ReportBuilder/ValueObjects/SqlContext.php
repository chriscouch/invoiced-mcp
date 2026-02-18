<?php

namespace App\Reports\ReportBuilder\ValueObjects;

final class SqlContext
{
    public function __construct(
        private array $parameters = [],
        private array $queryParams = [],
        private array $tableAliases = [],
    ) {
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    public function addParam(mixed $value): void
    {
        $this->queryParams[] = $value;
    }

    public function addParams(array $values): void
    {
        $this->queryParams = array_merge($this->queryParams, $values);
    }

    public function getTableAlias(Table $table): string
    {
        $tableAlias = $table->alias;
        if (!isset($this->tableAliases[$tableAlias])) {
            $this->tableAliases[$tableAlias] = str_replace(['.', '-'], ['_', '_'], $tableAlias).'_'.(count($this->tableAliases) + 1);
        }

        return $this->tableAliases[$tableAlias];
    }

    public function getParams(): array
    {
        return $this->queryParams;
    }
}
