<?php

namespace App\Tests\Integrations\Plaid;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\Utils\DebugContext;
use App\Integrations\Plaid\Libs\PlaidApi;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Tests\AppTestCase;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\CarbonImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use stdClass;

class PlaidApiTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    private function getClient(Client $client): PlaidApi
    {
        $plaid = new PlaidApi('client_id', 'secret', false, Mockery::mock(CloudWatchLogsClient::class), new DebugContext('test'));
        $plaid->setClient($client);
        $plaid->setLogger(self::$logger);

        return $plaid;
    }

    public function testExchangeToken(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"access_token":"super secret"}'),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $plaid = $this->getClient($client);

        $this->assertEquals((object) ['access_token' => 'super secret'], $plaid->exchangePublicToken(self::$company, 'pub_token'));
    }

    public function testExchangeTokenFail(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to process Plaid token.');

        $mock = new MockHandler([
            new Response(500),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $plaid = $this->getClient($client);

        $plaid->exchangePublicToken(self::$company, 'pub_token');
    }

    public function testGetTransactions(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                [],
                '{
                         "transactions": [{
                            "account_id": "XA96y1wW3xS7wKyEdbRzFkpZov6x1ohxMXwep",
                            "amount": 78.5,
                            "iso_currency_code": "USD",
                            "unofficial_currency_code": null
                          }, {
                            "account_id": "vokyE5Rn6vHKqDLRXEn5fne7LwbKPLIXGK98d",
                            "amount": 2307.21,
                            "iso_currency_code": "USD",
                            "unofficial_currency_code": null
                           }],
                          "total_transactions": 501
                        }'
            ),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $plaid = $this->getClient($client);

        $plaidItem = new PlaidItem();
        $plaidItem->access_token = 'tok_test';
        $plaidItem->account_id = '123';
        $plaidItem->saveOrFail();

        $account = new CashApplicationBankAccount();
        $account->data_starts_at = time();
        $account->plaid_link = $plaidItem;
        $account->saveOrFail();

        $transaction1 = new stdClass();
        $transaction1->account_id = 'XA96y1wW3xS7wKyEdbRzFkpZov6x1ohxMXwep';
        $transaction1->amount = 78.5;
        $transaction1->iso_currency_code = 'USD';
        $transaction1->unofficial_currency_code = null;

        $transaction2 = new stdClass();
        $transaction2->account_id = 'vokyE5Rn6vHKqDLRXEn5fne7LwbKPLIXGK98d';
        $transaction2->amount = 2307.21;
        $transaction2->iso_currency_code = 'USD';
        $transaction2->unofficial_currency_code = null;

        $resp = new stdClass();
        $resp->transactions = [$transaction1, $transaction2];
        $resp->total_transactions = 501;

        $this->assertEquals($resp, $plaid->getTransactions($plaidItem, CarbonImmutable::now()->subDay(), CarbonImmutable::now(), 500, 0));
    }
}
