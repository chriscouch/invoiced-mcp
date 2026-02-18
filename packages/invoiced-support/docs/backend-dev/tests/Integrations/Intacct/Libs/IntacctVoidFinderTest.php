<?php

namespace App\Tests\Integrations\Intacct\Libs;

use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Libs\IntacctVoidFinder;
use App\Tests\AppTestCase;
use Intacct\Xml\Response\Result;

class IntacctVoidFinderTest extends AppTestCase
{
    private const FIELDS = [
        'RECORDNO',
        'CUSTOMERID',
        'STATE',
        'CURRENCY',
        'RECEIPTDATE',
        'DOCNUMBER',
        'RECORDID',
        'PAYMENTTYPE',
        'INVOICES',
        'CREDITS',
        'AUWHENCREATED',
    ];

    private static string $xmlDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$xmlDIR = dirname(__DIR__).'/xml/intacct_void_finder';
    }

    public function testFindMatch(): void
    {
        $client = \Mockery::mock(IntacctApi::class);
        $client->shouldReceive('setAccount');

        /** @var \SimpleXMLElement $queryResult */
        $queryResult = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/query_result.xml'));
        $client->shouldReceive('getPayments')
            ->withArgs([['RECORDNO', 'STATE'], 'RECORDNO != "0004" AND (WHENMODIFIED = "08/26/2021 16:46:42" OR WHENMODIFIED = "08/26/2021 16:46:43")', 100])
            ->andReturn(new Result($queryResult->{'operation'}->{'result'}));

        /** @var \SimpleXMLElement $payment0Data */
        $payment0Data = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/result_1.xml'));
        $payment0 = (new Result($payment0Data->{'operation'}->{'result'}))->getData()[0];
        $client->shouldReceive('getPayment')
            ->withArgs(['0000', self::FIELDS])
            ->andReturn($payment0);

        /** @var \SimpleXMLElement $payment1Data */
        $payment1Data = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/result_2.xml'));
        $payment1 = (new Result($payment1Data->{'operation'}->{'result'}))->getData()[0];
        $client->shouldReceive('getPayment')
            ->withArgs(['0001', self::FIELDS])
            ->andReturn($payment1);

        /** @var \SimpleXMLElement $payment2Data */
        $payment2Data = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/result_3.xml'));
        $payment2 = (new Result($payment2Data->{'operation'}->{'result'}))->getData()[0];
        $client->shouldReceive('getPayment')
            ->withArgs(['0002', self::FIELDS])
            ->andReturn($payment2);

        /** @var \SimpleXMLElement $reversalData */
        $reversalData = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/reversal.xml'));
        $reversal = (new Result($reversalData->{'operation'}->{'result'}))->getData()[0];

        $voidFinder = new IntacctVoidFinder($client);
        $this->assertEquals($payment0, $voidFinder->findMatch($reversal, self::$intacctAccount));
    }
}
