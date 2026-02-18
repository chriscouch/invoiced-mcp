<?php

namespace App\Tests\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Intacct\Extractors\IntacctCustomerExtractor;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;
use Mockery;

class IntacctCustomerExtractorTest extends AppTestCase
{
    private static string $xmlDIR;
    private static IntacctSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasIntacctAccount();

        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();

        self::$xmlDIR = dirname(__DIR__).'/xml/intacct_customer_importer';

        self::$company->features->enable('multi_currency');
    }

    protected function getExtractor(IntacctApi $api): IntacctCustomerExtractor
    {
        return new IntacctCustomerExtractor($api);
    }

    public function testGetObjects(): void
    {
        $customers = (string) file_get_contents(self::$xmlDIR.'/intacct_customer_importer_customers.xml');

        $intacct = Mockery::mock(IntacctApi::class);
        $intacct->shouldReceive('setAccount');
        $intacct->shouldReceive('getCustomers')
            ->withArgs([true, [], "WHENMODIFIED >= '03/19/2030 23:00:00'"])
            ->once()
            ->andReturn(new Result(simplexml_load_string($customers)->{'operation'}->{'result'})); /* @phpstan-ignore-line */

        $extractor = $this->getExtractor($intacct);
        $lastSynced = (new CarbonImmutable('2030-03-20'))->setTime(0, 0);
        $query = new ReadQuery($lastSynced);
        $extractor->initialize(self::$intacctAccount, self::$syncProfile);

        $generator = $extractor->getObjects(self::$syncProfile, $query);

        $results = iterator_to_array($generator);

        $this->assertCount(4, $results);
    }
}
