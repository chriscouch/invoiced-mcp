<?php

namespace App\Tests\Integrations\Avalara;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\DebugContext;
use App\Integrations\Avalara\AvalaraApi;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\SalesTax\ValueObjects\SalesTaxInvoice;
use App\SalesTax\ValueObjects\SalesTaxInvoiceItem;
use App\Tests\AppTestCase;
use Avalara\AvaTaxClient;
use Avalara\CreateOrAdjustTransactionModel;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use CommerceGuys\Addressing\Address;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;

class AvalaraTaxCalculatorTest extends AppTestCase
{
    private static string $jsonDIR = __DIR__.'/json/avalara_tax_calculator';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasAvalaraAccount();

        self::hasItem();
        self::$item->avalara_tax_code = '1234';
        self::$item->avalara_location_code = 'NY';
        self::$item->saveOrFail();
    }

    private function getTaxCalculator(AvaTaxClient $client): AvalaraTaxCalculator
    {
        $api = new AvalaraApi(Mockery::mock(CloudWatchLogsClient::class), new DebugContext('test'), 'sandbox');
        $api->setClient($client);
        $api->setStatsd(new StatsdClient());

        return new AvalaraTaxCalculator($api);
    }

    private function getSalesTaxInvoice(): SalesTaxInvoice
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->number = 'CUST-00001';
        $address = new Address('US', 'TX', 'Austin', '', '78735', '', '5301 Southwest Parkway', 'Suite 470');

        $line1 = new SalesTaxInvoiceItem('Line 1', 1, 10000);
        $line2 = new SalesTaxInvoiceItem('Line 2', 4, 20000, self::$item->id);

        return new SalesTaxInvoice($customer, $address, 'usd', [$line1, $line2]);
    }

    public function testAssessItemLocationCode(): void
    {
        $client = Mockery::mock(AvaTaxClient::class);
        $client->shouldReceive('createOrAdjustTransaction')
            ->andReturnUsing(function ($include, CreateOrAdjustTransactionModel $transactionModel) {
                $model = json_decode((string) json_encode($transactionModel), true);
                $expectedJSON = (string) file_get_contents(self::$jsonDIR.'/avalara_tax_calculator_expected.json');
                $expected = json_decode($expectedJSON, true);
                $expected['createTransactionModel']['date'] = date('Y-m-d');
                $this->assertEquals($expected, $model);

                return (object) ['totalTax' => 5];
            })
            ->once();
        $taxCalculator = $this->getTaxCalculator($client);

        $result = $taxCalculator->assess($this->getSalesTaxInvoice());

        $expected = [
            [
                'tax_rate' => AvalaraTaxCalculator::TAX_CODE,
                '_calculated' => true,
                'amount' => 500,
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testVoidDocumentNotFound(): void
    {
        $responseJson = '{"error":{"code":"EntityNotFoundError","message":"Document not found.","target":"HttpRequest","details":[{"code":"EntityNotFoundError","number":4,"message":"Document not found.","description":"The Document with code \'INVOICEDINC:DINV-00391\' was not found.","faultCode":"Client","helpLink":"http://developer.avalara.com/avatax/errors/EntityNotFoundError","severity":"Error"}]}}';
        $exception = new ClientException('test', new Request('POST', '/'), new Response(400, [], $responseJson));

        $client = Mockery::mock(AvaTaxClient::class);
        $client->shouldReceive('voidTransaction')
            ->andThrow($exception);
        $taxCalculator = $this->getTaxCalculator($client);

        $taxCalculator->void($this->getSalesTaxInvoice());

        // should not throw an exception
    }
}
