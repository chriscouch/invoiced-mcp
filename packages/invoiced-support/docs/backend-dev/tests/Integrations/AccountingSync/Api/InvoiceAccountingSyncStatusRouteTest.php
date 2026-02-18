<?php

namespace App\Tests\Integrations\AccountingSync\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Api\InvoiceAccountingSyncStatusRoute;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class InvoiceAccountingSyncStatusRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getRoute(): InvoiceAccountingSyncStatusRoute
    {
        return new InvoiceAccountingSyncStatusRoute();
    }

    public function testNoMapping(): void
    {
        $request = new Request([], [], ['model_id' => self::$invoice->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Invoice::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'synced' => false,
            'error' => null,
        ];
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function testLegacyMapping(): void
    {
        self::$invoice->metadata = (object) ['quickbooks_invoice_id' => '456'];
        self::$invoice->saveOrFail();

        $request = new Request([], [], ['model_id' => self::$invoice->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Invoice::class);
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
        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = self::$invoice;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $request = new Request([], [], ['model_id' => self::$invoice->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Invoice::class);
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
