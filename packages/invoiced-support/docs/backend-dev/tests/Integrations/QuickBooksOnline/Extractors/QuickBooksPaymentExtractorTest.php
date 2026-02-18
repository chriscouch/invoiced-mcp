<?php

namespace App\Tests\Integrations\QuickBooksOnline\Extractors;

use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksPaymentExtractor;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Tests\AppTestCase;

class QuickBooksPaymentExtractorTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_payment_importer';
    }

    public function getClient(): QuickBooksApi
    {
        /**
         * Test Data.
         */
        $result1 = json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_payment_query_1.json'));
        $result2 = json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_payment_query_2.json'));
        $result3 = json_decode((string) file_get_contents(self::$jsonDIR.'/qbo_payment_query_3.json'));

        $client = \Mockery::mock(QuickBooksApi::class);
        $client->shouldReceive('setAccount');

        $client->shouldReceive('query')
            ->withArgs(['Payment', 1, ''])
            ->andReturn($result1->QueryResponse->Payment);
        $client->shouldReceive('query')
            ->withArgs(['Payment', 2, ''])
            ->andReturn($result2->QueryResponse->Payment);
        $client->shouldReceive('query')
            ->withArgs(['Payment', 3, ''])
            ->andReturn($result3->QueryResponse->Payment);
        $client->shouldReceive('query')
            ->withArgs(['Payment', 4, ''])
            ->andReturn([]);

        return $client;
    }

    public function testGetObjects(): void
    {
        $qbo = $this->getClient();

        $extractor = $this->getExtractor($qbo);

        $syncProfile = new QuickBooksOnlineSyncProfile();
        $syncProfile->read_pdfs = false;
        $extractor->initialize(new QuickBooksAccount(), $syncProfile);
        $query = new ReadQuery();

        $results = iterator_to_array($extractor->getObjects($syncProfile, $query));

        $this->assertCount(5, $results);
    }

    private function getExtractor(QuickBooksApi $api): QuickBooksPaymentExtractor
    {
        return new QuickBooksPaymentExtractor($api);
    }
}
