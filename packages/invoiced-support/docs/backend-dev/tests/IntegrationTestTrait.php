<?php

namespace App\Tests;

use Carbon\CarbonImmutable;
use App\Core\Orm\Model;

/**
 * Set of useful methods for integration testing.
 *
 * @method void assertEquals(mixed $a, mixed $b, string $s)
 */
trait IntegrationTestTrait
{
    private static array $modelDateProps = [
        'created_at',
        'contract_period_start',
        'contract_period_end',
        'date',
        'due_date',
        'period_start',
        'period_end',
        'renewed_last',
        'renews_next',
        'start_date',
    ];

    /**
     * Sets CarbonImmutable::now() return value to given value.
     */
    protected function mockTime(?string $timeString = null): void
    {
        if (!$timeString) {
            // reset time
            CarbonImmutable::setTestNow();

            return;
        }

        CarbonImmutable::setTestNow('@'.$this->getTimeFromFormat($timeString)->unix());
    }

    /**
     * Gets CarbonImmutable instance from custom formatted time string.
     *
     * @param string|int $value
     */
    protected function getTimeFromFormat($value): CarbonImmutable
    {
        // parse formatting
        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value);
        }

        return CarbonImmutable::createFromTimeString($value);
    }

    /*
     * Stringifies a model so it can be printed to the console.
     */
    protected function outputModel(Model $model): string
    {
        $array = $model->toArray();

        // Convert date properties
        foreach ($array as $key => &$value) {
            if ($value && in_array($key, self::$modelDateProps)) {
                $value = CarbonImmutable::createFromTimestamp($value);
                $value = $value->format('Y-m-d H:i:s');
            }
        }

        return (string) json_encode($array, JSON_PRETTY_PRINT);
    }

    /**
     * Returns a 1-dimensional array of array keys. Nested arrays
     * are represented by joining each level with a '.'
     * as such: "base_key.nest_key.key".
     */
    protected function getArrayKeys(array $a): array
    {
        $keys = [];
        foreach (array_keys($a) as $key) {
            // non-nested key
            if (!is_array($a[$key])) {
                $keys[] = $key;
                continue;
            }

            // nested key
            $subKeys = $this->getArrayKeys($a[$key]);
            foreach ($subKeys as $subKey) {
                $keys[] = $key.'.'.$subKey;
            }
        }

        return $keys;
    }

    /**
     * Asserts that the value in array $a at the given key is equal to the value
     * in array $b at the given key.
     *
     * NOTE: Nested keys are represented by joining the two levels via '.'
     * Example:
     *  $a = [
     *     'level1' => [
     *         'key' => true
     *     ]
     *  ]
     * can be asserted against $b w/ the key 'level1.key'
     *
     * @param array|object|null $b
     */
    protected function assertArrayKeyEquals(string $key, array $a, $b, string $message): void
    {
        // expected to exist in class inheriting the trait
        $this->assertEquals($this->getNestedKeyValue($key, $a), $this->getNestedKeyValue($key, $b), $message);
    }

    /**
     * Gets an array value from a nested key. I.e key = 'level1.value'.
     *
     * @param array|object|null $a
     */
    protected function getNestedKeyValue(string $key, $a): mixed
    {
        $_a = $a;
        $parts = explode('.', $key);
        foreach ($parts as $part) {
            if (is_object($_a)) {
                $_a = $_a->$part ?? null;
            } else {
                $_a = $_a[$part] ?? null;
            }
        }

        return $_a;
    }
}
