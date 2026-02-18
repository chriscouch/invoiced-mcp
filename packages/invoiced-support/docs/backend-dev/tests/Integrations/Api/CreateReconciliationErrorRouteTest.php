<?php

namespace App\Tests\Integrations\Api;

use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Api\CreateReconciliationErrorRoute;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class CreateReconciliationErrorRouteTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCreditNote();
        self::hasPayment();

        $mapping = new AccountingCustomerMapping();
        $mapping->customer = self::$customer;
        $mapping->integration_id = 2;
        $mapping->accounting_id = '2';
        $mapping->source = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = self::$invoice;
        $mapping->integration_id = 2;
        $mapping->accounting_id = '2';
        $mapping->source = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = self::$creditNote;
        $mapping->integration_id = 2;
        $mapping->accounting_id = '2';
        $mapping->source = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $mapping = new AccountingPaymentMapping();
        $mapping->payment = self::$payment;
        $mapping->integration_id = 2;
        $mapping->accounting_id = '2';
        $mapping->source = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();
    }

    private function getRoute(): CreateReconciliationErrorRoute
    {
        return new CreateReconciliationErrorRoute();
    }

    /**
     * @testWith ["customer"]
     *           ["invoice"]
     *           ["credit_note"]
     *           ["payment"]
     */
    public function testInputWithoutObjectId(string $object): void
    {
        $request = new Request();
        $request->request->add([
            'accounting_id' => 1,
            'integration_id' => 2,
            'message' => 'test',
            'object' => $object,
        ]);
        $route = $this->getRoute();
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->buildResponse($context);

        /** @var ReconciliationError[] $errors */
        $errors = ReconciliationError::where('accounting_id', 1)
            ->where('integration_id', 2)
            ->where('object', $object)
            ->execute();
        $this->assertCount(1, $errors);
        $this->assertEquals('test', $errors[0]->message);
        $this->assertEquals(null, $errors[0]->object_id);
    }

    /**
     * @testWith ["customer"]
     *           ["invoice"]
     *           ["credit_note"]
     *           ["payment"]
     */
    public function testInputWithoutExistingObjectId(string $object): void
    {
        $objectId = match ($object) {
            'customer' => self::$customer->id,
            'invoice' => self::$invoice->id,
            'credit_note' => self::$creditNote->id,
            'payment' => self::$payment->id,
            default => throw new \Exception('Invalid value'),
        };

        $input = [
            'accounting_id' => 2,
            'integration_id' => 2,
            'message' => 'test2',
            'object' => $object,
        ];

        $request = new Request();
        $request->request->add($input);
        $route = $this->getRoute();
        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->buildResponse($context);

        /** @var ReconciliationError[] $errors */
        $errors = ReconciliationError::where('accounting_id', 2)
            ->where('integration_id', 2)
            ->where('object', $object)
            ->where('object_id', $objectId)
            ->execute();
        $this->assertCount(1, $errors);
        $this->assertEquals('test2', $errors[0]->message);
    }
}
