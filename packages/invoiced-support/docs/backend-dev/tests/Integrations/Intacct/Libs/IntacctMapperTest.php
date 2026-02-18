<?php

namespace App\Tests\Integrations\Intacct\Libs;

use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Tests\AppTestCase;
use SimpleXMLElement;

class IntacctMapperTest extends AppTestCase
{
    private function getMapper(): IntacctMapper
    {
        return new IntacctMapper();
    }

    public function testGetNestedXmlValue(): void
    {
        $mapper = $this->getMapper();

        /** @var SimpleXMLElement $xml */
        $xml = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?>
<response>
    <TERM>
        <NAME>Net 30</NAME>
        <MORE>
            <NESTING>deep</NESTING>
        </MORE>
    </TERM>
    <MESSAGE>Test</MESSAGE>
</response>');

        $this->assertNull($mapper->getNestedXmlValue($xml, 'doesnotexist'));
        $this->assertNull($mapper->getNestedXmlValue($xml, 'TERM.MORE.NO'));
        $this->assertEquals('Test', $mapper->getNestedXmlValue($xml, 'MESSAGE'));
        $this->assertEquals('Net 30', $mapper->getNestedXmlValue($xml, 'TERM.NAME'));
        $this->assertEquals('deep', $mapper->getNestedXmlValue($xml, 'TERM.MORE.NESTING'));
    }
}
