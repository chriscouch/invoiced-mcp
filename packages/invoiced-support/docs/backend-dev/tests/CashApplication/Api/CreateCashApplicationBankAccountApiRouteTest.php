<?php

namespace App\Tests\CashApplication\Api;

use App\CashApplication\Api\CreateCashApplicationBankAccountRoute;
use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Integrations\Plaid\Libs\AddPlaidItem;
use App\Integrations\Plaid\Libs\PlaidApi;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class CreateCashApplicationBankAccountApiRouteTest extends AppTestCase
{
    public function testCreate(): void
    {
        self::hasCompany();
        $api = Mockery::mock(PlaidApi::class);
        $api->shouldReceive('exchangePublicToken')->andReturn((object) [
            'access_token' => 'test',
            'item_id' => 'item_id',
        ]);
        $route = new CreateCashApplicationBankAccountRoute($api, $this->getService('test.tenant'), new AddPlaidItem());

        $request = new Request();
        $context = new ApiCallContext($request, [], [
            'metadata' => [
                'accounts' => [
                    'account' => [
                        'subtype' => 'checking',
                        'name' => 'test',
                        'mask' => 'test',
                        'id' => 'id',
                        'type' => 'type',
                        'verification_status' => 'verification_status',
                    ],
                ],
                'institution' => [
                    'name' => 'name',
                    'institution_id' => 'test',
                ],
            ],
            'token' => 'test',
            'start_date' => 'now',
        ], $route->getDefinition());
        $route->buildResponse($context);

        $this->assertEquals(1, CashApplicationBankAccount::query()->count());
        $this->assertEquals(1, PlaidItem::query()->count());

        // validation in effect
        $route->buildResponse($context);
        $this->assertEquals(1, CashApplicationBankAccount::query()->count());
        $this->assertEquals(1, PlaidItem::query()->count());

        // simulate adding it via vendor pay
        $this->getService('test.database')->delete('CashApplicationBankAccounts', [
            'tenant_id' => self::$company->id,
        ]);

        $route->buildResponse($context);
        $this->assertEquals(1, CashApplicationBankAccount::query()->count());
        $this->assertEquals(2, PlaidItem::query()->count());
    }
}
