<?php

namespace App\Tests\Integrations\NetSuite\Libs;

use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\NetSuite\Libs\NetSuiteApi;
use App\Integrations\NetSuite\Libs\NetSuiteRetry;
use App\Tests\AppTestCase;

class NetSuiteRetryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testRetry(): void
    {
        $tenantContext = \Mockery::mock(TenantContext::class);
        $tenantContext->shouldReceive('get')->andReturn(self::$company);
        $netSuiteApi = \Mockery::mock(NetSuiteApi::class);
        $retry = new NetSuiteRetry($netSuiteApi, $tenantContext);

        $data = [
            'object' => 'invoice',
            'accounting_id' => 1,
            'object_id' => 2,
        ];

        $retry->retry($data);

        self::hasNetSuiteAccount();
        $netSuiteApi->shouldReceive('callRestlet')
            ->withSomeOfArgs([
                'id' => 1,
                'object' => 'invoice',
            ])
            ->andReturn((object) [])->once();

        $retry->retry($data);

        $netSuiteApi->shouldHaveReceived('callRestlet')->once();
        $this->assertEquals(0, ReconciliationError::where('object', 'invoice')
            ->where('accounting_id', 1)
            ->where('object_id', 2)
            ->count()
        );

        $netSuiteApi->shouldReceive('callRestlet')
            ->withSomeOfArgs([
                'id' => 1,
                'object' => 'invoice',
            ])
            ->andReturn((object) [
            'error' => 'test',
        ])->once();

        $retry->retry($data);

        $errors = ReconciliationError::where('object', 'invoice')
            ->where('accounting_id', 1)
            ->where('object_id', 2)
            ->execute();
        $this->assertCount(1, $errors);
        $error = $errors[0]->toArray();
        $this->assertEquals(1, $error['accounting_id']);
        $this->assertEquals(1, $error['description']);
        $this->assertEquals('error', $error['level']);
        $this->assertEquals('test', $error['message']);
        $this->assertEquals('invoice', $error['object']);
        $this->assertEquals(2, $error['object_id']);
        $errors[0]->delete();

        $netSuiteApi->shouldReceive('callRestlet')
            ->withSomeOfArgs([
                'id' => 1,
                'object' => 'invoice',
            ])
            ->andThrow(new IntegrationApiException('test2'));

        $retry->retry($data);

        $errors = ReconciliationError::where('object', 'invoice')
            ->where('accounting_id', 1)
            ->where('object_id', 2)
            ->execute();
        $this->assertCount(1, $errors);
        $error = $errors[0]->toArray();
        $this->assertEquals(1, $error['accounting_id']);
        $this->assertEquals(1, $error['description']);
        $this->assertEquals('error', $error['level']);
        $this->assertEquals('test2', $error['message']);
        $this->assertEquals('invoice', $error['object']);
        $this->assertEquals(2, $error['object_id']);
    }
}
