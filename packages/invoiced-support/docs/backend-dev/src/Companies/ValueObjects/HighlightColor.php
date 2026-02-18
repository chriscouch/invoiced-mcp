<?php

/*
 Modified from csscolor.php
 Copyright 2004 Patrick Fitzgerald
 http://www.barelyfitz.com/projects/csscolor/

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace App\Companies\ValueObjects;

use Exception;
use InvalidArgumentException;

class HighlightColor
{
    const HEX_COLOR_6DIGIT = '/^#?([a-fA-F0-9][a-fA-F0-9])([a-fA-F0-9][a-fA-F0-9])([a-fA-F0-9][a-fA-F0-9])$/';

    const HEX_COLOR_3DIGIT = '/^#?([a-fA-F0-9])([a-fA-F0-9])([a-fA-F0-9])$/';

    private string $color;
    private string $foreground;

    /**
     * @param string $color         hex color code without '#'
     * @param int    $minBrightDiff minimum brightness difference
     * @param int    $minColorDiff  minimum color difference
     *
     * @throws InvalidArgumentException when the hex value is invalid
     */
    public function __construct(string $color, /**
     * brightDiff is the minimum brightness difference
     * between the background and the foreground.
     */
    private int $minBrightDiff = 126, /**
     * colorDiff is the minimum color difference
     * between the background and the foreground.
     */
    private int $minColorDiff = 500)
    {
        // Make sure we got a valid hex value
        if (!$this->isHex($color)) {
            throw new InvalidArgumentException("Color '$color' is not a hex color value");
        }

        // Set the parameters
        $this->color = $color;
    }

    /**
     * Gets the highlight color.
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Gets the foreground color.
     */
    public function getForeground(): string
    {
        // Generate the foreground color
        if (!isset($this->foreground)) {
            $this->foreground = $this->calcFG($this->color, 'fff');
        }

        return $this->foreground;
    }

    /**
     * Gets the minimum brightness difference setting.
     */
    public function getMinBrightDiff(): int
    {
        return $this->minBrightDiff;
    }

    /**
     * Gets the color brightness difference setting.
     */
    public function getMinColorDiff(): int
    {
        return $this->minColorDiff;
    }

    /**
     * Lightens a color.
     *
     * @param string $hex color code
     */
    public function lighten(string $hex, float $percent): string
    {
        return $this->mix($hex, $percent, 255);
    }

    /**
     * Darkens a color.
     *
     * @param string $hex color code
     */
    public function darken(string $hex, float $percent): string
    {
        return $this->mix($hex, $percent, 0);
    }

    /**
     * Mixes a color.
     *
     * @param string $hex color code
     *
     * @throws InvalidArgumentException when inputs are invalid
     */
    public function mix(string $hex, float $percent, int $mask): string
    {
        // Make sure inputs are valid
        if (!is_numeric($percent) || $percent < 0 || $percent > 1) {
            throw new InvalidArgumentException("percent=$percent is not valid");
        }

        if (!is_int($mask) || $mask < 0 || $mask > 255) {
            throw new InvalidArgumentException("mask=$mask is not valid");
        }

        $rgb = $this->hex2RGB($hex);
        for ($i = 0; $i < 3; ++$i) {
            $rgb[$i] = round($rgb[$i] * $percent) + round($mask * (1 - $percent));

            // In case rounding up causes us to go to 256
            if ($rgb[$i] > 255) {
                $rgb[$i] = 255;
            }
        }

        return $this->RGB2Hex($rgb);
    }

    /**
     * Given a hex color (rrggbb or rgb),
     * returns an array (r, g, b) with decimal values
     * If $hex is not the correct format,
     * returns false.
     *
     * @param string $hex color code
     *
     * @throws Exception when the hex value is invalid
     */
    public function hex2RGB(string $hex): array
    {
        // Make sure $hex is valid
        if (preg_match(self::HEX_COLOR_6DIGIT, $hex, $rgb)) {
            return [
                hexdec($rgb[1]),
                hexdec($rgb[2]),
                hexdec($rgb[3]),
            ];
        }

        if (preg_match(self::HEX_COLOR_3DIGIT, $hex, $rgb)) {
            return [
                hexdec($rgb[1].$rgb[1]),
                hexdec($rgb[2].$rgb[2]),
                hexdec($rgb[3].$rgb[3]),
            ];
        }

        throw new Exception("Cannot convert hex '$hex' to RGB");
    }

    /**
     * Given an [rval,gval,bval] consisting of
     * decimal color values (0-255), returns a hex string
     * suitable for use with CSS.
     *
     * @param array $rgb RGB values
     *
     * @throws InvalidArgumentException when the RGB value is invalid
     */
    public function RGB2Hex(array $rgb): string
    {
        // Make sure the input is valid
        if (!$this->isRGB($rgb)) {
            throw new InvalidArgumentException('RGB value is not valid');
        }

        $hex = '';
        for ($i = 0; $i < 3; ++$i) {
            // Convert the decimal digit to hex
            $hexDigit = dechex($rgb[$i]);

            // Add a leading zero if necessary
            if (1 == strlen($hexDigit)) {
                $hexDigit = '0'.$hexDigit;
            }

            // Append to the hex string
            $hex .= $hexDigit;
        }

        // Return the complete hex string
        return $hex;
    }

    /**
     * Returns true if $hex is a valid CSS hex color.
     *
     * @param string $hex color code
     */
    public function isHex(string $hex): bool
    {
        return preg_match(self::HEX_COLOR_6DIGIT, $hex) ||
               preg_match(self::HEX_COLOR_3DIGIT, $hex);
    }

    /**
     * Returns true if $rgb is an array with three valid
     * decimal color digits.
     */
    public function isRGB(array $rgb): bool
    {
        if (3 != count($rgb)) {
            return false;
        }

        for ($i = 0; $i < 3; ++$i) {
            // Get the decimal digit
            $dec = intval($rgb[$i]);

            // Make sure the decimal digit is between 0 and 255
            if (!is_int($dec) || $dec < 0 || $dec > 255) {
                return false;
            }
        }

        return true;
    }

    /**
     * Given a background color and a foreground color,
     * modifies the foreground color so it will have enough
     * contrast to be seen against the background color.
     *
     * The following class parameters are used:
     * $this->minBrightDiff
     * $this->minColorDiff
     *
     * Loop through brighter and darker versions
     * of the foreground color.
     * The numbers here represent the amount of
     * foreground color to mix with black and white.
     *
     * @param string $bgHex color code
     * @param string $fgHex color code
     */
    public function calcFG(string $bgHex, string $fgHex): string
    {
        $steps = [1, 0.75, 0.5, 0.25, 0];
        foreach ($steps as $percent) {
            $darker = $this->darken($fgHex, $percent);
            $lighter = $this->lighten($fgHex, $percent);

            $darkerBrightDiff = $this->brightnessDiff($bgHex, $darker);
            $lighterBrightDiff = $this->brightnessDiff($bgHex, $lighter);

            if ($lighterBrightDiff > $darkerBrightDiff) {
                $color = $lighter;
                $brightDiff = $lighterBrightDiff;
            } else {
                $color = $darker;
                $brightDiff = $darkerBrightDiff;
            }
            $colorDiff = $this->colorDiff($bgHex, $color);

            if ($brightDiff >= $this->minBrightDiff &&
    $colorDiff >= $this->minColorDiff) {
                break;
            }
        }

        return $color;
    }

    /**
     * Returns the brightness value for a color.
     *
     * To allow for maximum readability, the difference between
     * the background brightness and the foreground brightness
     * should be greater than 125.
     *
     * @param string $hex color code
     *
     * @return int between 0 and 178
     */
    public function brightness(string $hex): int
    {
        $rgb = $this->hex2RGB($hex);

        return (int) ((($rgb[0] * 299) + ($rgb[1] * 587) + ($rgb[2] * 114)) / 1000);
    }

    /**
     * Returns the brightness value for a color.
     *
     * To allow for maximum readability, the difference between
     * the background brightness and the foreground brightness
     * should be greater than 125.
     *
     * @param string $hex1 color code
     * @param string $hex2 color code
     *
     * @return int between 0 and 178
     */
    public function brightnessDiff(string $hex1, string $hex2): int
    {
        $b1 = $this->brightness($hex1);
        $b2 = $this->brightness($hex2);

        return abs($b1 - $b2);
    }

    /**
     * Returns the contrast between two colors,
     * an integer between 0 and 675.
     *
     * To allow for maximum readability, the difference between
     * the background and the foreground color should be > 500.
     *
     * @param string $hex1 color code
     * @param string $hex2 color code
     */
    public function colorDiff(string $hex1, string $hex2): int
    {
        $rgb1 = $this->hex2RGB($hex1);
        $rgb2 = $this->hex2RGB($hex2);

        [$r1, $g1, $b1] = $rgb1;
        [$r2, $g2, $b2] = $rgb2;

        return (int) (abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2));
    }
}
