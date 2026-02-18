<?php

namespace App\Core\Utils;

use DateTimeInterface;

class InfuseUtility
{
    /**
     * Generates a unique 32-digit GUID. i.e. 12345678-1234-5678-123456789012.
     */
    public static function guid(bool $dashes = true): string
    {
        if (function_exists('com_create_guid')) {
            return trim('{}', (string) com_create_guid());
        }
        $charid = strtoupper(md5(uniqid((string) rand(), true)));

        $dash = $dashes ? '-' : '';

        $uuid = substr($charid, 0, 8).$dash.
                substr($charid, 8, 4).$dash.
                substr($charid, 12, 4).$dash.
                substr($charid, 16, 4).$dash.
                substr($charid, 20, 12);

        return $uuid;
    }

    /**
     * Formats a number with a set number of decimals and a metric suffix
     * i.e. number_abbreviate( 12345, 2 ) -> 12.35K.
     */
    public static function numberAbbreviate(int $number, int $decimals = 1): string
    {
        $abbrevs = [
            24 => 'Y',
            21 => 'Z',
            18 => 'E',
            15 => 'P',
            12 => 'T',
            9 => 'G',
            6 => 'M',
            3 => 'K',
            0 => '',
        ];

        foreach ($abbrevs as $exponent => $abbrev) {
            if ($number >= pow(10, $exponent)) {
                $remainder = $number % pow(10, $exponent);
                $decimal = ($remainder > 0) ? round(round($remainder, $decimals) / pow(10, $exponent), $decimals) : 0;

                return (intval($number / pow(10, $exponent)) + $decimal).$abbrev;
            }
        }

        return (string) $number;
    }

    /**
     * Converts a UNIX timestamp to the database timestamp format.
     */
    public static function unixToDb(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Generates a string for how long ago a timestamp happened.
     * i.e. '2 minutes ago' or 'just now'.
     *
     * @param bool $full true: time ago has every granularity, false: time ago has biggest granularity only
     */
    public static function timeAgo(int $timestamp, bool $full = false): string
    {
        $now = new \DateTime();
        $ago = new \DateTime();
        $ago->setTimestamp($timestamp);

        $string = self::timeDiff($now, $ago);

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }

        return $string ? implode(', ', $string).' ago' : 'just now';
    }

    /**
     * Calculates the time difference between two DateTime objects
     * Borrowed from http://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago.
     *
     * @return array delta at time each granularity
     */
    private static function timeDiff(DateTimeInterface $now, DateTimeInterface $then): array
    {
        $interval = $now->diff($then);

        $w = floor($interval->d / 7);
        $interval->d -= (int) ($w * 7);

        $diff = [
            'y' => $interval->y,
            'm' => $interval->m,
            'w' => $w,
            'd' => $interval->d,
            'h' => $interval->h,
            'i' => $interval->i,
            's' => $interval->s,
        ];

        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
        foreach ($string as $k => &$v) {
            if ($diff[$k]) {
                $v = $diff[$k].' '.$v.($diff[$k] > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        return $string;
    }
}
