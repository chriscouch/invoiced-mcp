<?php

namespace App\Tests\Integrations\AccountingSync\Api;

use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Api\PaymentAccountingSyncStatusRoute;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class PaymentAccountingSyncStatusRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasPayment();
    }

    private function getRoute(): PaymentAccountingSyncStatusRoute
    {
        return new PaymentAccountingSyncStatusRoute();
    }

    public function testNoMapping(): void
    {
        $request = new Request([], [], ['model_id' => self::$payment->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Payment::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'synced' => false,
            'error' => null,
        ];
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function testWithMapping(): void
    {
        $mapping = new AccountingPaymentMapping();
        $mapping->payment = self::$payment;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingPaymentMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $request = new Request([], [], ['model_id' => self::$payment->id()]);
        $route = $this->getRoute();
        $route->setModelClass(Payment::class);
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
