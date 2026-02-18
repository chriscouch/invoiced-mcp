<?php

namespace App\Tests\Integrations\QuickBooksOnline\Extractors;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\QuickBooksOnline\Extractors\QuickBooksCreditMemoExtractor;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksCreditMemoExtractorTest extends AppTestCase
{
    private static string $jsonDIR;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$jsonDIR = dirname(__DIR__).'/json';
    }

    private function getClient(): QuickBooksApi
    {
        /**
         * Test Data.
         */
        $result1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_credit_memo_importer/qbo_credit_memo_query_1.json');
        $result2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_credit_memo_importer/qbo_credit_memo_query_2.json');
        $result3 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_credit_memo_importer/qbo_credit_memo_query_3.json');

        // The CreditMemo test data has the same CustomerRef value for each CreditMemo
        $customer = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer/quickbooks_invoice_importer_customer_1.json');

        // Line items data is the same in the credit memo data as it is in the invoice data.
        // qbo_credit_memo_query_1 has the same lines as quickbooks_invoice_importer_result_1 etc.
        // In QBO, the line objects do not differ between invoices and credit memos
        $bundle1 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer/quickbooks_invoice_importer_bundle_1.json');
        $bundle2 = (string) file_get_contents(self::$jsonDIR.'/quickbooks_invoice_importer/quickbooks_invoice_importer_bundle_2.json');

        $client = Mockery::mock(QuickBooksApi::class);
        $client->shouldReceive('setAccount');
        $client->shouldReceive('query')
            ->withArgs(['CreditMemo', 1, "Balance > '0'"])
            ->andReturn(json_decode($result1)->QueryResponse->CreditMemo);
        $client->shouldReceive('query')
            ->withArgs(['CreditMemo', 2, "Balance > '0'"])
            ->andReturn(json_decode($result2)->QueryResponse->CreditMemo);
        $client->shouldReceive('query')
            ->withArgs(['CreditMemo', 3, "Balance > '0'"])
            ->andReturn(json_decode($result3)->QueryResponse->CreditMemo);
        $client->shouldReceive('query')
            ->withArgs(['CreditMemo', 4, "Balance > '0'"])
            ->andReturn([]);
        $client->shouldReceive('getCustomer')
            ->withArgs([1]) // The one customer reference used has an Id of 1.
            ->andReturn(json_decode($customer)->Customer);
        $client->shouldReceive('getItem')
            ->withArgs([201])
            ->andReturn(json_decode($bundle1)->Item);
        $client->shouldReceive('getItem')
            ->withArgs([206])
            ->andReturn(json_decode($bundle2)->Item);

        return $client;
    }

    private function getExtractor(QuickBooksApi $api): QuickBooksCreditMemoExtractor
    {
        return new QuickBooksCreditMemoExtractor($api);
    }

    public function testGetObjects(): void
    {
        $qbo = $this->getClient();

        $extractor = $this->getExtractor($qbo);

        $syncProfile = new QuickBooksOnlineSyncProfile();
        $syncProfile->read_pdfs = false;
        $extractor->initialize(new QuickBooksAccount(), $syncProfile);
        $query = new ReadQuery(openItemsOnly: true);

        $results = iterator_to_array($extractor->getObjects($syncProfile, $query));

        $this->assertCount(3, $results);
    }
}
