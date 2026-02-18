<?php

namespace App\Core\Utils;

use App\Core\Utils\Exception\CompressionException;

/**
 * Helper class to compress data using Gzip.
 * PHP will use ZLIB for the compression method.
 */
class Compression
{
    /**
     * Compresses the given data into raw bytes.
     *
     * If an ASCII-friendly version is needed then
     * it is recommended to base64 encode the result.
     *
     * @throws CompressionException
     */
    public static function compress(string $data): string
    {
        $result = gzencode($data, 9);
        if (!$result) {
            throw new CompressionException('Could not compress data');
        }

        return $result;
    }

    /**
     * Decompresses data that was previously compressed by this class.
     *
     * @throws CompressionException
     */
    public static function decompress(string $data): string
    {
        $result = gzdecode($data);
        if (!$result) {
            throw new CompressionException('Could not decompress data');
        }

        return $result;
    }

    /**
     * Checks if a string has been compressed by this class.
     */
    public static function isCompressed(string $data): bool
    {
        // Per https://datatracker.ietf.org/doc/html/rfc1952 the
        // first 3 bytes identify if a string was gzipped.
        return 0 === mb_strpos($data, "\x1f"."\x8b"."\x08", 0, 'US-ASCII');
    }

    /**
     * This is a shortcut to decompress data if it has
     * been compressed. The reason that the data might
     * not be compressed is if it was stored prior to
     * compression being utilized.
     *
     * @throws CompressionException
     */
    public static function decompressIfNeeded(string $data): string
    {
        return self::isCompressed($data) ?
            self::decompress($data) :
            $data;
    }
}
