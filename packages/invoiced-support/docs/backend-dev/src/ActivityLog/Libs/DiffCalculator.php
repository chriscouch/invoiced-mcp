<?php

namespace App\ActivityLog\Libs;

class DiffCalculator
{
    /**
     * Returns the difference between 2 arrays/objects, recursively.
     *
     * @param array|object $obj1
     * @param array|object $obj2
     *
     * @return array|object
     */
    public function diff($obj1, $obj2)
    {
        // ensure $obj2 is always an array
        $obj2Array = (array) $obj2;
        $obj1Array = (array) $obj1;

        $diff = [];
        // value was added
        foreach ($obj2Array as $key => $item) {
            if (!isset($obj1Array[$key]) && $item) {
                $diff[$key] = null;
            }
        }

        foreach ($obj1Array as $k => $value) {
            // value was either added or removed
            if (!isset($obj2Array[$k]) && null !== $value) {
                // value was removed
                $diff[$k] = $value;
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $newDiff = $this->diff($value, $obj2Array[$k]);
                if (!empty((array) $newDiff)) {
                    if (is_array($value)) {
                        // check if array is numeric
                        $keys = array_keys($value);
                        if (count($keys) === count(array_filter($keys, 'is_numeric'))) {
                            $diff[$k] = $value;
                            continue;
                        }
                    }
                    $diff[$k] = $newDiff;
                }
                continue;
            }

            if (isset($obj2Array[$k]) && !$this->scalarsAreSame($obj2Array[$k], $value)) {
                $diff[$k] = $value;
            }
        }

        // maintain types
        if (1 === count($diff) && isset($diff['updated_at'])) {
            return [];
        }

        return is_array($obj1) ? $diff : (object) $diff;
    }

    /**
     * Checks if 2 scalar values are equal. This is a modified
     * === check that treats floats and ints as the same type.
     */
    private function scalarsAreSame(mixed $value1, mixed $value2): bool
    {
        // convert int to float for interchangeable comparison
        if (is_int($value1)) {
            $value1 = (float) $value1;
        }

        if (is_int($value2)) {
            $value2 = (float) $value2;
        }

        return $value1 === $value2;
    }
}
