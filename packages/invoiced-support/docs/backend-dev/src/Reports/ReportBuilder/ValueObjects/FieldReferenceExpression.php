<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;

final class FieldReferenceExpression implements ExpressionInterface
{
    private mixed $withValue = null;

    public function __construct(
        public readonly Table $table,
        public readonly string $id,
        private ?ColumnType $type = null,
        private ?string $name = null,
        public readonly ?string $metadataObject = null,
        private bool $shouldSummarize = false,
        public readonly string $dateFormat = 'U', // UNIX timestamp is default date format
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): ?ColumnType
    {
        return $this->type;
    }

    public function getSelectAlias(): string
    {
        return str_replace(['.', '-'], ['_', '_'], $this->id);
    }

    public function shouldSummarize(): bool
    {
        return $this->shouldSummarize;
    }

    public function withValue(mixed $value): void
    {
        $this->withValue = $value;
    }

    public function queryValue(): mixed
    {
        return $this->withValue;
    }
}
