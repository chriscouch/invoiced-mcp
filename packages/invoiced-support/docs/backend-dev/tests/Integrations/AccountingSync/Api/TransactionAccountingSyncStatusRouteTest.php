<?php

namespace App\Tests\Integrations\AccountingSync\Api;

use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\Api\TransactionAccountingSyncStatusRoute;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class TransactionAccountingSyncStatusRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();
    }

    private function getRoute(): TransactionAccountingSyncStatusRoute
    {
        return new TransactionAccountingSyncStatusRoute();
    }

    public function testNoMapping(): void
    {
        $request = new Request([], [], ['model_id' => self::$transaction->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Transaction::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'synced' => false,
            'error' => null,
        ];
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function testLegacyMapping(): void
    {
        self::$transaction->metadata = (object) ['quickbooks_payment_id' => '456'];
        self::$transaction->saveOrFail();

        $request = new Request([], [], ['model_id' => self::$transaction->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Transaction::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'synced' => true,
            'accounting_system' => 'quickbooks_online',
            'accounting_id' => '456',
            'source' => 'accounting_system',
            'first_synced' => null,
            'last_synced' => null,
            'error' => null,
        ];
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function testWithMapping(): void
    {
        $mapping = new AccountingTransactionMapping();
        $mapping->transaction = self::$transaction;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingTransactionMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $request = new Request([], [], ['model_id' => self::$transaction->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Transaction::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'synced' => true,
            'accounting_system' => 'intacct',
            'accounting_id' => '1234',
            'source' => 'invoiced',
            'first_synced' => $mapping->created_at,
            'last_synced' => $mapping->updated_at,
            'error' => null,
        ];
        $this->assertEquals($expected, $route->buildResponse($context));
    }
}
