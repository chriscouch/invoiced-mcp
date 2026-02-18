<?php

namespace App\Tests\Network\Ubl;

use App\Network\Ubl\UblJsonTransformer;
use App\Tests\AppTestCase;

class UblJsonTransformerTest extends AppTestCase
{
    public function getTransformer(): UblJsonTransformer
    {
        return new UblJsonTransformer();
    }

    public function testInvoice(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/data/output/invoice.xml');
        $output = $this->getTransformer()->transform($xml);
        $this->assertJsonStringEqualsJsonString((string) file_get_contents(__DIR__.'/data/output/invoice.json'), (string) json_encode($output));
    }

    public function testEstimate(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/data/output/quote.xml');
        $output = $this->getTransformer()->transform($xml);
        $this->assertJsonStringEqualsJsonString((string) file_get_contents(__DIR__.'/data/output/quote.json'), (string) json_encode($output));
    }

    public function testCreditNote(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/data/output/credit-note.xml');
        $output = $this->getTransformer()->transform($xml);
        $this->assertJsonStringEqualsJsonString((string) file_get_contents(__DIR__.'/data/output/credit-note.json'), (string) json_encode($output));
    }

    public function testBalanceForwardStatement(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/data/output/balance-forward-statement.xml');
        $output = $this->getTransformer()->transform($xml);
        $this->assertJsonStringEqualsJsonString((string) file_get_contents(__DIR__.'/data/output/balance-forward-statement.json'), (string) json_encode($output));
    }

    public function testOpenItemStatement(): void
    {
        $xml = (string) file_get_contents(__DIR__.'/data/output/open-item-statement.xml');
        $output = $this->getTransformer()->transform($xml);
        $this->assertJsonStringEqualsJsonString((string) file_get_contents(__DIR__.'/data/output/open-item-statement.json'), (string) json_encode($output));
    }
}
