<?php

/**
 * Looks up a key in an array. If the key follows dot-notation then a nested lookup will be performed.
 * i.e. users.sherlock.address.lat -> ['users']['sherlock']['address']['lat'].
 */
function array_value(array $input = [], string $key = ''): mixed
{
    if (array_key_exists($key, $input)) {
        return $input[$key];
    }

    $pieces = explode('.', $key);

    // use dot notation to search a nested array
    if (count($pieces) > 1) {
        foreach ($pieces as $piece) {
            if (!is_array($input) || !array_key_exists($piece, $input)) {
                // not found
                return null;
            }

            $input = &$input[$piece];
        }

        return $input;
    }

    return null;
}

/**
 * Sets an element in an array using dot notation (i.e. fruit.apples.qty sets ['fruit']['apples']['qty'].
 */
function array_set(array &$array, string $key, mixed $value): mixed
{
    $pieces = explode('.', $key);

    foreach ($pieces as $k => $piece) {
        $array = &$array[$piece];
        if (!is_array($array)) {
            $array = [];
        }
    }

    return $array = $value;
}

/**
 * Flattens a multi-dimensional array using dot notation
 * i.e. ['fruit' => ['apples' => ['qty' => 1]]] produces
 * [fruit.apples.qty => 1].
 */
function array_dot(array $array, string $prefix = ''): array
{
    $result = [];

    if (!empty($prefix)) {
        $prefix = $prefix.'.';
    }

    foreach ($array as $k => $v) {
        if (is_array($v)) {
            $result = array_replace(
                $result,
                array_dot($v, $prefix.$k));
        } else {
            $result[$prefix.$k] = $v;
        }
    }

    return $result;
}
