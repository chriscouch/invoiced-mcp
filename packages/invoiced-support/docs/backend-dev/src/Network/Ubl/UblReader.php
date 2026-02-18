<?php

namespace App\Network\Ubl;

use RuntimeException;
use SimpleXMLElement;

final class UblReader
{
    /**
     * The names used for the namespaces can change. We must detect this
     * and transform to the prefixes we expect, like cac: and cbc:.
     */
    private const NAMESPACES = [
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ];

    public static function parse(string $data): SimpleXMLElement
    {
        $xml = simplexml_load_string($data);
        if (!($xml instanceof SimpleXMLElement)) {
            throw new RuntimeException('Could not parse xml');
        }

        self::registerXpathNamespaces($xml);

        return $xml;
    }

    public static function registerXpathNamespaces(SimpleXMLElement $xml): void
    {
        // Register root namespace by making an assumption
        // off of the name of the root namespace.
        $type = $xml->getName();
        $xml->registerXPathNamespace('doc', "urn:oasis:names:specification:ubl:schema:xsd:$type-2");

        // Register XML namespaces for use with XPath
        foreach (self::NAMESPACES as $prefix => $namespace) {
            $xml->registerXPathNamespace($prefix, $namespace);
        }
    }

    /**
     * Performs an xpath search on given XML element and returns
     * the results.
     */
    public static function xpath(SimpleXMLElement $xml, string $xpath): array
    {
        $result = $xml->xpath($xpath);
        if (!$result) {
            return [];
        }

        foreach ($result as $xml) {
            self::registerXpathNamespaces($xml);
        }

        return $result;
    }

    /**
     * Performs an xpath search on given XML element and returns
     * the first result.
     */
    public static function xpathToSingle(SimpleXMLElement $xml, string $xpath): ?SimpleXMLElement
    {
        $result = $xml->xpath($xpath);
        if (!$result) {
            return null;
        }

        self::registerXpathNamespaces($result[0]);

        return $result[0];
    }

    /**
     * Performs an xpath search on given XML element and converts
     * it to a string value.
     */
    public static function xpathToString(SimpleXMLElement $xml, string $xpath): ?string
    {
        $result = $xml->xpath($xpath);

        return $result ? (string) $result[0] : null;
    }
}
