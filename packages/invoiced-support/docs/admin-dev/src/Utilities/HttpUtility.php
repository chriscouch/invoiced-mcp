<?php

namespace App\Utilities;

use DOMDocument;
use Throwable;

class HttpUtility
{
    public function decodeCompressedBody(string $input): string
    {
        $decoded = (string) gzinflate(base64_decode($input));

        // Authorize.Net adds a UTF-8 BOM to the response that
        // must be stripped in order to JSON decode it.
        $possibleBOM = substr($decoded, 0, 3);
        $utfBOM = pack('CCC', 0xEF, 0xBB, 0xBF);
        if (0 === strncmp($possibleBOM, $utfBOM, 3)) {
            $jsonDecoded = json_decode(substr($decoded, 3));
        } else {
            $jsonDecoded = json_decode($decoded);
        }

        // pretty print JSON
        if ($jsonDecoded) {
            return (string) json_encode($jsonDecoded, JSON_PRETTY_PRINT);
        }

        // pretty print XML
        if (false !== strpos($decoded, '<?xml') || false !== strpos($decoded, 'xmlns')) {
            try {
                $dom = new DOMDocument('1.0');
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                if (@$dom->loadXML($decoded)) {
                    return (string) $dom->saveXML();
                }
            } catch (Throwable) {
                // ignore errors
            }
        }

        return $decoded;
    }
}
