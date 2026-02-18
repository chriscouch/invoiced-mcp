<?php

namespace App\Tests\Integrations\QuickBooksOnline\Extractors;

use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksCustomerExtractor;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksCustomerExtractorTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_customer_importer';
    }

    private function getExtractor(QuickBooksApi $api): QuickBooksCustomerExtractor
    {
        return new QuickBooksCustomerExtractor($api);
    }

    private function buildApiClient(): QuickBooksApi
    {
        $customers1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_customer_importer_customers_1.json');

        $customers2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_customer_importer_customers_2.json');

        $customers3 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_customer_importer_customers_3.json');

        $qbo = Mockery::mock(QuickBooksApi::class);
        $qbo->shouldReceive('setAccount');

        $qbo->shouldReceive('query')
            ->withArgs(['Customer', 1, 'Active IN (true, false)'])
            ->andReturn(json_decode($customers1)->QueryResponse->Customer);

        $qbo->shouldReceive('query')
            ->withArgs(['Customer', 2, 'Active IN (true, false)'])
            ->andReturn(json_decode($customers2)->QueryResponse->Customer);

        $qbo->shouldReceive('query')
            ->withArgs(['Customer', 3, 'Active IN (true, false)'])
            ->andReturn(json_decode($customers3)->QueryResponse->Customer);

        $qbo->shouldReceive('query')
            ->withArgs(['Customer', 4, 'Active IN (true, false)'])
            ->andReturn([]);

        return $qbo;
    }

    public function testGetObjects(): void
    {
        $qbo = $this->buildApiClient();

        $extractor = $this->getExtractor($qbo);
        $syncProfile = new QuickBooksOnlineSyncProfile();
        $extractor->initialize(new QuickBooksAccount(), $syncProfile);
        $query = new ReadQuery();

        $results = iterator_to_array($extractor->getObjects($syncProfile, $query));

        $this->assertCount(3, $results);
    }
}
