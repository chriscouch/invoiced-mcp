<?php

namespace App\Tests\Integrations\QuickBooksOnline\Extractors;

use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksInvoiceExtractor;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksInvoiceExtractorTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$jsonDIR = dirname(__DIR__).'/json/quickbooks_invoice_importer';
    }

    private function getExtractor(QuickBooksApi $api): QuickBooksInvoiceExtractor
    {
        return new QuickBooksInvoiceExtractor($api);
    }

    private function buildApiClient(): QuickBooksApi
    {
        $result1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_result_1.json');
        $result2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_result_2.json');
        $result3 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_result_3.json');

        $customer1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_customer_1.json');
        $customer2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_customer_2.json');
        $customer3 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_customer_3.json');

        $bundle1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_bundle_1.json');
        $bundle2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_bundle_2.json');

        $empty = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_empty.json');
        $query = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer_query.json');

        $qbo = Mockery::mock(QuickBooksApi::class);
        $qbo->shouldReceive('setAccount');
        $qbo->shouldReceive('query')
            ->withArgs(['Invoice', 1, "Balance > '0'"])
            ->andReturn(json_decode($result1)->QueryResponse->Invoice);

        $qbo->shouldReceive('query')
            ->withArgs(['Invoice', 2, "Balance > '0'"])
            ->andReturn(json_decode($result2)->QueryResponse->Invoice);

        $qbo->shouldReceive('query')
            ->withArgs(['Invoice', 3, "Balance > '0'"])
            ->andReturn(json_decode($empty)->QueryResponse->Invoice);

        $qbo->shouldReceive('query')
            ->withArgs(['Invoice', 4, "Balance > '0'"])
            ->andReturn([]);

        $qbo->shouldReceive('getCustomer')
            ->withArgs([1])
            ->andReturn(json_decode($customer1)->Customer);

        $qbo->shouldReceive('getCustomer')
            ->withArgs([2])
            ->andReturn(json_decode($customer2)->Customer);

        $qbo->shouldReceive('getCustomer')
            ->withArgs([3])
            ->andReturn(json_decode($customer3)->Customer);

        $qbo->shouldReceive('getItem')
            ->withArgs([201])
            ->andReturn(json_decode($bundle1)->Item);

        $qbo->shouldReceive('getItem')
            ->withArgs([206])
            ->andReturn(json_decode($bundle2)->Item);

        $qbo->shouldReceive('getTerm')
            ->withArgs(['3'])
            ->andReturn((object) ['Name' => 'NET 30']);

        $qbo->shouldReceive('getTerm')
            ->withArgs(['6'])
            ->andReturn((object) ['Name' => 'NET 45']);

        return $qbo;
    }

    public function testGetObjects(): void
    {
        $qbo = $this->buildApiClient();

        $extractor = $this->getExtractor($qbo);
        $syncProfile = new QuickBooksOnlineSyncProfile();
        $syncProfile->read_pdfs = false;
        $extractor->initialize(new QuickBooksAccount(), $syncProfile);
        $query = new ReadQuery(openItemsOnly: true);

        $results = iterator_to_array($extractor->getObjects($syncProfile, $query));

        $this->assertCount(2, $results);
    }
}
