<?php

namespace App\Core\Orm;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Exception;
use IteratorAggregate;
use Traversable;

/**
 * Represents a collection of models.
 */
final class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    /** @var Model[] */
    private array $models;

    /**
     * @param Model[] $models
     */
    public function __construct(array $models)
    {
        $this->models = $models;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->models);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->models[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->models[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        // collections are immutable
        throw new Exception('Cannot perform set on immutable Collection');
    }

    public function offsetUnset($offset): void
    {
        // collections are immutable
        throw new Exception('Cannot perform unset on immutable Collection');
    }

    public function count(): int
    {
        return count($this->models);
    }
}
