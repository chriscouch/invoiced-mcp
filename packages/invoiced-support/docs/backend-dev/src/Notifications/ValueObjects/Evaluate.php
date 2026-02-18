<?php

namespace App\Notifications\ValueObjects;

use App\ActivityLog\Models\Event;

/**
 * Evaluates all conditions given and returns yes or no for notifying.
 *
 * @deprecated
 */
class Evaluate
{
    const OPERATOR_EQUAL = 'equal';
    const OPERATOR_DOES_NOT_EQUAL = 'doesNotEqual';
    const OPERATOR_GREATER_THAN = 'greaterThan';
    const OPERATOR_GREATER_THAN_OR_EQUAL_TO = 'greaterThanOrEqualTo';
    const OPERATOR_LESS_THAN = 'lessThan';
    const OPERATOR_LESS_THAN_OR_EQUAL_TO = 'lessThanOrEqualTo';
    const OPERATOR_CONTAINS = 'contains';
    const OPERATOR_DOES_NOT_CONTAIN = 'doesNotContain';
    const OPERATOR_IS_SET = 'isSet';
    const OPERATOR_IS_NOT_SET = 'isNotSet';

    public function __construct(private Rule $rule, private Event $event)
    {
    }

    /**
     * Evaluate all conditions given by the rule.
     */
    public function evaluate(): bool
    {
        $notify = true;

        foreach ($this->rule->getConditions() as $condition) {
            $operator = call_user_func_array([$this, $condition->getOperator()], [$condition]); /* @phpstan-ignore-line */
            if (Rule::MATCH_ANY == $this->rule->getMatch()) {
                if ($operator) {
                    return true;
                }
                $notify = false;
            }
            if (Rule::MATCH_ALL == $this->rule->getMatch() && !$operator) {
                return false;
            }
        }

        return $notify;
    }

    /**
     * Check if property equals comparison.
     */
    public function equal(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return $value == $comparison;
    }

    /**
     * Check if property does not equal comparison.
     */
    public function doesNotEqual(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return $value != $comparison;
    }

    /**
     * Check if property is greater than comparison.
     */
    public function greaterThan(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return $value > $comparison;
    }

    /**
     * Check if property is greater than or equal to comparison.
     */
    public function greaterThanOrEqualTo(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return $value >= $comparison;
    }

    /**
     * check if property is less than comparison.
     */
    public function lessThan(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return $value < $comparison;
    }

    /**
     * Check if property is less than or equal to comparison.
     */
    public function lessThanOrEqualTo(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return $value <= $comparison;
    }

    /**
     * Check if property contains the comparison.
     */
    public function contains(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return str_contains($value, $comparison);
    }

    /**
     * Check if property does not contain comparison.
     */
    public function doesNotContain(Condition $condition): bool
    {
        [$value, $comparison] = $this->getProperties($condition);

        return !str_contains($value, $comparison);
    }

    /**
     * Check if property is set.
     */
    public function isSet(Condition $condition): bool
    {
        $first = $condition->getProperty();
        $nesting = explode('.', $first);

        if (1 == count($nesting)) {
            return array_key_exists($nesting[0], (array) $this->event->object);
        }
        $data = json_decode((string) json_encode($this->event->toArray()['data']), true); // recursively convert to array
        foreach ($nesting as $key => $nest) {
            if ((count($nesting) - 1) == $key) {
                return array_key_exists($nest, $data);
            }
            $data = $data[$nest];
        }

        return false;
    }

    /**
     * Check if property is not set.
     */
    public function isNotSet(Condition $condition): bool
    {
        $first = $condition->getProperty();
        $nesting = explode('.', $first);

        if (1 == count($nesting)) {
            return !array_key_exists($nesting[0], (array) $this->event->object);
        }
        $data = json_decode((string) json_encode($this->event->toArray()['data']), true); // recursively convert to array
        foreach ($nesting as $key => $nest) {
            if ((count($nesting) - 1) == $key) {
                return !array_key_exists($nest, $data);
            }
            $data = $data[$nest];
        }

        return true;
    }

    /**
     * Gets the event value and comparison value.
     *
     * @return array [event value, comparison value)
     */
    private function getProperties(Condition $condition): array
    {
        $event = json_decode((string) json_encode($this->event->toArray()['data']), true); // recursively convert to array
        $event['user_id'] = $this->event->user_id;
        $property = $condition->getProperty();
        $value = array_value($event, $property);

        $comparison = $condition->getComparison();
        if ($condition->getComparisonObject()) {
            $comparison_name = $condition->getComparison();
            $comparison = array_value($event, $comparison_name);
        }

        return [$value, $comparison];
    }
}
