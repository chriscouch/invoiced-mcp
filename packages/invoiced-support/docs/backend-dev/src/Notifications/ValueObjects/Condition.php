<?php

namespace App\Notifications\ValueObjects;

use JsonSerializable;

/**
 * Takes the property to be checked, operator and comparison.
 *
 * @deprecated
 */
class Condition implements JsonSerializable, \Stringable
{
    /**
     * @param string     $property          property that contains the value we want to compare
     * @param string     $operator          comparison operator
     * @param mixed|null $comparison        value we are comparing to, if any
     * @param bool       $comparison_object when true, the comparison value comes from a property
     */
    public function __construct(private string $property, private string $operator, private $comparison = null, private bool $comparison_object = true)
    {
    }

    /**
     * Return property.
     */
    public function getProperty(): string
    {
        return $this->property;
    }

    /**
     * Return Operator.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Return comparison.
     *
     * @return mixed|null
     */
    public function getComparison()
    {
        return $this->comparison;
    }

    /**
     * Return comparison object.
     */
    public function getComparisonObject(): bool
    {
        return $this->comparison_object;
    }

    public function jsonSerialize(): object
    {
        $obj = new \stdClass();
        $obj->property = $this->getProperty();
        $obj->operator = $this->getOperator();
        $obj->comparison = $this->getComparison();
        $obj->comparison_object = $this->getComparisonObject();

        return $obj;
    }

    public function __toString(): string
    {
        return (string) json_encode($this);
    }

    /**
     * Builds a condition from a serialized condition string.
     */
    public static function fromString(string $string): self
    {
        return self::fromObject(json_decode($string));
    }

    /**
     * Builds a condition from a deserialized condition string.
     */
    public static function fromObject(\stdClass $obj): self
    {
        return new self($obj->property, $obj->operator, $obj->comparison, $obj->comparison_object);
    }
}
