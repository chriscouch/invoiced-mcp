<?php

namespace App\Tests\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Intacct\Extractors\IntacctPaymentExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;

class IntacctPaymentExtractorTest extends AppTestCase
{
    private static string $xmlDIR;
    private static IntacctSyncProfile $syncProfile;

    private const FIELDS = [
        'RECORDNO',
        'CUSTOMERID',
        'CUSTOMERNAME',
        'STATE',
        'CURRENCY',
        'RECEIPTDATE',
        'DOCNUMBER',
        'RECORDID',
        'PAYMENTTYPE',
        'INVOICES',
        'CREDITS',
        'AUWHENCREATED',
        'MEGAENTITYID',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasIntacctAccount();
        self::hasCustomer();

        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->integration_version = 2;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();

        self::$xmlDIR = dirname(__DIR__).'/xml/intacct_payment_importer';
    }

    private function getExtractor(IntacctApi $api): IntacctPaymentExtractor
    {
        return new IntacctPaymentExtractor($api);
    }

    public function testExtract(): void
    {
        $intacct = \Mockery::mock(IntacctApi::class);
        $intacct->shouldReceive('setAccount');

        /** @var \SimpleXMLElement $payments1 */
        $payments1 = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/intacct_payments_1.xml'));
        /** @var \SimpleXMLElement $payments2 */
        $payments2 = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/intacct_payments_2.xml'));

        $intacct->shouldReceive('getPayments')
            ->withArgs([['RECORDNO'], "WHENMODIFIED >= '03/19/2030 23:00:00' AND RECEIPTDATE >= '03/19/2015'"])
            ->once()
            ->andReturn(new Result($payments1->{'operation'}->{'result'}));

        $intacct->shouldReceive('getPaymentsByIds')
            ->withArgs([['0000', '0001', '0002'], self::FIELDS])
            ->once()
            ->andReturn(new Result($payments2->{'operation'}->{'result'}));

        $extractor = $this->getExtractor($intacct);
        $lastSynced = (new CarbonImmutable('2030-03-20'))->setTime(0, 0);
        $startDate = new CarbonImmutable('2015-03-19');
        $query = new ReadQuery($lastSynced, $startDate);
        $extractor->initialize(self::$intacctAccount, self::$syncProfile);

        $generator = $extractor->getObjects(self::$syncProfile, $query);

        $results = iterator_to_array($generator);

        $this->assertCount(3, $results);
    }
}
