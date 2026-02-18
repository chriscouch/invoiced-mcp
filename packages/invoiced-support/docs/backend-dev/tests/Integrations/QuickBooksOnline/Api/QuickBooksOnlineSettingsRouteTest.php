<?php

namespace App\Tests\Integrations\QuickBooksOnline\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\QuickBooksOnline\Api\QuickBooksOnlineSettingsRoute;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\Services\QuickbooksOnline;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class QuickBooksOnlineSettingsRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
    }

    public function testBuildResponse(): void
    {
        $qboAccount = Mockery::mock(QuickBooksAccount::class);
        $integrationFactory = Mockery::mock(IntegrationFactory::class);
        $integration = Mockery::mock(QuickbooksOnline::class);
        $integration->shouldReceive('isConnected')
            ->andReturn(true);
        $integration->shouldReceive('getAccount')
            ->andReturn($qboAccount);
        $integrationFactory->shouldReceive('get')
            ->andReturn($integration);
        $quickBooksApi = Mockery::mock(QuickBooksApi::class);
        $quickBooksApi->shouldReceive('setAccount');
        $quickBooksApi->shouldReceive('getTaxCodes')
            ->andReturn([]);
        $quickBooksApi->shouldReceive('getPreferences')
            ->andReturn((object) [
                'SalesFormsPrefs' => (object) [
                    'CustomField' => [],
                ],
            ]);

        $tenantContext = self::getService('test.tenant');

        $route = new QuickBooksOnlineSettingsRoute($integrationFactory, $quickBooksApi, $tenantContext);

        $definition = new ApiRouteDefinition(null, null, []);
        $request = new Request();
        $context = new ApiCallContext($request, [], [], $definition);

        $accountMock = (object) [
            'Name' => 'Income',
            'FullyQualifiedName' => 'Income',
            'AccountType' => 'Income',
            'SubAccount' => 'true',
        ];

        $quickBooksApi->shouldReceive('getChartOfAccounts')
            ->with(1)
            ->andReturn([])->once();
        $response = $route->buildResponse($context);
        $this->assertCount(0, $response['income_accounts']);

        $quickBooksApi->shouldReceive('getChartOfAccounts')
            ->with(1)
            ->andReturn([$accountMock])->once();
        $response = $route->buildResponse($context);
        $this->assertCount(1, $response['income_accounts']);

        $quickBooksApi->shouldReceive('getChartOfAccounts')
            ->with(1)
            ->andReturn(array_map(fn ($v) => $accountMock, range(1, 1000)))
            ->once();
        $quickBooksApi->shouldReceive('getChartOfAccounts')
            ->with(1001)
            ->andReturn(array_map(fn ($v) => $accountMock, range(1, 1000)))
            ->once();
        $quickBooksApi->shouldReceive('getChartOfAccounts')
            ->with(2001)
            ->andReturn(array_map(fn ($v) => $accountMock, range(1, 999)))
            ->once();
        $response = $route->buildResponse($context);
        $this->assertCount(2999, $response['income_accounts']);
    }
}
