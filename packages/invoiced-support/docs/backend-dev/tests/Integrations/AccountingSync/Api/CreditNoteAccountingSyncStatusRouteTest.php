<?php

namespace App\Tests\Integrations\AccountingSync\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Integrations\AccountingSync\Api\CreditNoteAccountingSyncStatusRoute;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class CreditNoteAccountingSyncStatusRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCreditNote();
    }

    private function getRoute(): CreditNoteAccountingSyncStatusRoute
    {
        return new CreditNoteAccountingSyncStatusRoute();
    }

    public function testNoMapping(): void
    {
        $request = new Request([], [], ['model_id' => self::$creditNote->id()]);
        $route = $this->getRoute();
        $route->setModelClass(CreditNote::class);
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());

        $expected = [
            'synced' => false,
            'error' => null,
        ];
        $this->assertEquals($expected, $route->buildResponse($context));
    }

    public function testWithMapping(): void
    {
        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = self::$creditNote;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '1234';
        $mapping->source = AccountingCreditNoteMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $request = new Request([], [], ['model_id' => self::$creditNote->id()]);
        $route = $this->getRoute();
        $route->setModelClass(CreditNote::class);
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
