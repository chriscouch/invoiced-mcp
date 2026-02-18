<?php

namespace App\Network\Ubl;

use SimpleXMLElement;
use stdClass;

/**
 * Transforms an XML UBL document to JSON.
 */
class UblJsonTransformer
{
    private const IGNORED_KEYS = ['EmbeddedDocumentBinaryObject'];

    public function transform(string $input): stdClass
    {
        $output = new stdClass();
        /** @var SimpleXMLElement $xml */
        $xml = simplexml_load_string($input);
        /** @var array $namespaces */
        $namespaces = $xml->getDocNamespaces();
        $this->addElement($xml, $output, $namespaces);

        return $output;
    }

    private function addElement(SimpleXMLElement $element, stdClass $output, array $namespaces): void
    {
        $children = [];
        foreach ($namespaces as $namespace) {
            foreach ($element->children($namespace) as $child) {
                $children[] = $child;
            }
        }

        // Check if leaf node
        if (0 == count($children)) {
            // Add value
            $output->{'_'} = (string) $element;

            // Add attributes
            foreach ($element->attributes() as $key => $value) {
                $output->$key = (string) $value;
            }

            return;
        }

        // Recursively add child nodes
        foreach ($children as $child) {
            $subOutput = new stdClass();
            $this->addElement($child, $subOutput, $namespaces);
            $k = $child->getName();
            if (in_array($k, self::IGNORED_KEYS)) {
                continue;
            }
            if (!isset($output->$k)) {
                $output->$k = [];
            }
            $output->$k[] = $subOutput;
        }
    }
}
