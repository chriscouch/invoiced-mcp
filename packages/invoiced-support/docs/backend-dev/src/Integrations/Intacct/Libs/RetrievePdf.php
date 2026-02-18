<?php

namespace App\Integrations\Intacct\Libs;

use Intacct\Functions\AbstractFunction;
use Intacct\Xml\XMLWriter;

class RetrievePdf extends AbstractFunction
{
    private string $docId;

    public function getDocId(): string
    {
        return $this->docId;
    }

    public function setDocId(string $docId): void
    {
        $this->docId = $docId;
    }

    public function writeXml(XMLWriter &$xml): void
    {
        $xml->startElement('function');
        $xml->writeAttribute('controlid', $this->getControlId());

        $xml->startElement('retrievepdf');

        $xml->startElement('SODOCUMENT');

        $xml->writeElement('DOCID', $this->docId, true);

        $xml->endElement(); // SODOCUMENT

        $xml->endElement(); // retrievepdf

        $xml->endElement(); // function
    }
}
