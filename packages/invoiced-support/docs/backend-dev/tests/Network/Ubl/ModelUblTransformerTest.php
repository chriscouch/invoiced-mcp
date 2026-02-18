<?php

namespace App\Tests\Network\Ubl;

use App\Network\Ubl\ModelUblTransformer;
use App\Network\Ubl\UblDocumentValidator;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ModelUblTransformerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$company->name = 'TEST & <Corp> Araújo';
        self::$company->website = 'https://invoiced.com';
        self::$company->phone = '12345678';
        self::$company->saveOrFail();
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->date = (new CarbonImmutable('2022-11-15'))->getTimestamp();
        self::$invoice->saveOrFail();
        self::hasUnappliedCreditNote();
        self::$creditNote->date = (new CarbonImmutable('2022-11-15'))->getTimestamp();
        self::$creditNote->saveOrFail();
        self::hasEstimate();
        self::$estimate->date = (new CarbonImmutable('2022-11-15'))->getTimestamp();
        self::$estimate->saveOrFail();
    }

    private function getTransformer(): ModelUblTransformer
    {
        return self::getService('test.model_ubl_transformer');
    }

    public function testInvoice(): void
    {
        $transformer = $this->getTransformer();
        $output = $transformer->transform(self::$invoice, ['pdf' => false]);
        $expectedXML = (string) file_get_contents(__DIR__.'/data/output/invoice.xml');
        $expectedXML = str_replace('{FROM_USERNAME}', self::$company->username, $expectedXML);
        $this->assertXmlStringEqualsXmlString($expectedXML, $output);
        $this->assertTrue($this->isValid($output));
    }

    public function testEstimate(): void
    {
        $transformer = $this->getTransformer();
        $output = $transformer->transform(self::$estimate, ['pdf' => false]);
        $expectedXML = (string) file_get_contents(__DIR__.'/data/output/quote.xml');
        $expectedXML = str_replace('{FROM_USERNAME}', self::$company->username, $expectedXML);
        $this->assertXmlStringEqualsXmlString($expectedXML, $output);
        $this->assertTrue($this->isValid($output));
    }

    public function testCreditNote(): void
    {
        $transformer = $this->getTransformer();
        $output = $transformer->transform(self::$creditNote, ['pdf' => false]);
        $expectedXML = (string) file_get_contents(__DIR__.'/data/output/credit-note.xml');
        $expectedXML = str_replace('{FROM_USERNAME}', self::$company->username, $expectedXML);
        $this->assertXmlStringEqualsXmlString($expectedXML, $output);
        $this->assertTrue($this->isValid($output));
    }

    public function testBalanceForwardStatement(): void
    {
        $transformer = $this->getTransformer();
        $statement = self::getService('test.statement_builder')->balanceForward(self::$customer, null, (new CarbonImmutable('2022-11-01'))->getTimestamp(), (new CarbonImmutable('2022-11-30'))->getTimestamp());
        $output = $transformer->transform($statement, ['pdf' => false]);
        $expectedXML = (string) file_get_contents(__DIR__.'/data/output/balance-forward-statement.xml');
        $expectedXML = str_replace('{FROM_USERNAME}', self::$company->username, $expectedXML);
        $this->assertXmlStringEqualsXmlString($expectedXML, $output);
        $this->assertTrue($this->isValid($output));
    }

    public function testOpenItemStatement(): void
    {
        $transformer = $this->getTransformer();
        $statement = self::getService('test.statement_builder')->openItem(self::$customer, null, (new CarbonImmutable('2022-11-28'))->getTimestamp());
        $output = $transformer->transform($statement, ['pdf' => false]);
        $expectedXML = (string) file_get_contents(__DIR__.'/data/output/open-item-statement.xml');
        $expectedXML = str_replace('{FROM_USERNAME}', self::$company->username, $expectedXML);
        $this->assertXmlStringEqualsXmlString($expectedXML, $output);
        $this->assertTrue($this->isValid($output));
    }

    private function isValid(string $xml): bool
    {
        $validator = new UblDocumentValidator(self::$kernel->getProjectDir());
        $validator->validate($xml);

        return true;
    }
}
