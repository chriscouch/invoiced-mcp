<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use ArrayAccess;
use Countable;
use RuntimeException;

final class ExpressionList implements ExpressionInterface, ArrayAccess, Countable
{
    /**
     * @param ExpressionInterface[] $expressions
     */
    public function __construct(public readonly array $expressions)
    {
    }

    public function getName(): ?string
    {
        $name = '';
        foreach ($this->expressions as $expression) {
            $name .= $expression->getName().' ';
        }

        return trim($name);
    }

    public function getType(): ?ColumnType
    {
        // Resolve the type based on the sub-expressions
        $type = null;
        foreach ($this->expressions as $expression) {
            $subType = $expression->getType();
            if ($subType && !$type) {
                $type = $subType;
            } elseif ($subType && $type != $subType) {
                // when there is a type conflict of sub-expressions immediately resolve to no type
                return null;
            }
        }

        return $type;
    }

    public function getSelectAlias(): string
    {
        return 'formula';
    }

    public function offsetExists($offset): bool
    {
        return isset($this->expressions[$offset]);
    }

    public function offsetGet($offset): ExpressionInterface
    {
        return $this->expressions[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('Expression lists are immutable');
    }

    public function offsetUnset($offset): void
    {
        throw new RuntimeException('Expression lists are immutable');
    }

    public function count(): int
    {
        return count($this->expressions);
    }
}
