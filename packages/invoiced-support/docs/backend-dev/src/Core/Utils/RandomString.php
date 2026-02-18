<?php

namespace App\Core\Utils;

use Random\Randomizer;

class RandomString
{
    const CHAR_LOWER = 'abcdefghijklmnopqrstuvwxyz';
    const CHAR_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const CHAR_ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const CHAR_NUMERIC = '0123456789';
    const CHAR_ALNUM = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private static Randomizer $randomizer;

    /**
     * Generate a random string.
     *
     * @see https://paragonie.com/b/JvICXzh_jhLyt4y3
     *
     * @param int    $length  - How long should our random string be?
     * @param string $charset - A string of all possible characters to choose from
     */
    public static function generate(int $length = 32, string $charset = self::CHAR_LOWER): string
    {
        if ($length < 1) {
            // Just return an empty string. Any value < 1 is meaningless.
            return '';
        }

        if (!isset(self::$randomizer)) {
            self::$randomizer = new Randomizer();
        }

        return sprintf(
            '%s',
            self::$randomizer->getBytesFromString($charset, $length),
        );
    }
}
