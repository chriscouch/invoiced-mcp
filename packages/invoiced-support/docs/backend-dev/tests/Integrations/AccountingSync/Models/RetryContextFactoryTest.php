<?php

namespace App\Tests\Integrations\AccountingSync\Models;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryContextFactory;
use App\Integrations\AccountingSync\ValueObjects\ReadRetryContext;
use App\Integrations\AccountingSync\ValueObjects\WriteRetryContext;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use App\Core\Orm\Event\ModelCreated;

class RetryContextFactoryTest extends AppTestCase
{
    private function getFactory(): RetryContextFactory
    {
        return new RetryContextFactory();
    }

    public function testRetryContextNone(): void
    {
        $error = new ReconciliationError();
        $error->integration_id = IntegrationType::Avalara->value;
        $this->assertNull($this->getFactory()->make($error));
    }

    public function testRetryContextInvoiced(): void
    {
        $typesToTest = [
            ObjectType::CreditNote,
            ObjectType::Customer,
            ObjectType::Invoice,
            ObjectType::Note,
            ObjectType::Payment,
            ObjectType::Transaction,
        ];

        $error = new ReconciliationError(['id' => 1]);
        $error->integration_id = IntegrationType::QuickBooksOnline->value;
        $error->object_id = 1234;
        $error->retry_context = (object) ['e' => ModelCreated::getName()];

        foreach ($typesToTest as $objectType) {
            $error->object = $objectType->typeName();
            /** @var WriteRetryContext $context */
            $context = $this->getFactory()->make($error);
            $this->assertInstanceOf(WriteRetryContext::class, $context);
            $this->assertFalse($context->fromAccountingSystem);
            $expected = [
                'id' => '1234',
                'class' => $objectType->modelClass(),
                'eventName' => ModelCreated::getName(),
                'accounting_system' => IntegrationType::QuickBooksOnline->value,
            ];
            $this->assertEquals($expected, $context->data);
            $this->assertEquals(1, $context->errorId);
        }
    }

    public function testRetryContextIntacct(): void
    {
        $data = ['test' => true];
        $error = new ReconciliationError(['id' => 1]);
        $error->integration_id = IntegrationType::Intacct->value;
        $error->accounting_id = '1234';
        $error->retry_context = (object) $data;
        $context = $this->getFactory()->make($error);
        $this->assertInstanceOf(ReadRetryContext::class, $context);
        $this->assertTrue($context->fromAccountingSystem);
        $this->assertEquals($data, $context->data);
        $this->assertEquals(1, $context->errorId);
    }

    public function testRetryContextQuickBooksDesktop(): void
    {
        $data = ['test' => true];
        $error = new ReconciliationError(['id' => 1]);
        $error->integration_id = IntegrationType::QuickBooksDesktop->value;
        $error->accounting_id = '1234';
        $error->retry_context = (object) $data;
        $context = $this->getFactory()->make($error);
        $this->assertNull($context);
    }

    public function testRetryContextQuickBooksOnline(): void
    {
        $data = ['test' => true];
        $error = new ReconciliationError(['id' => 1]);
        $error->integration_id = IntegrationType::QuickBooksOnline->value;
        $error->accounting_id = '1234';
        $error->retry_context = (object) $data;
        $context = $this->getFactory()->make($error);
        $this->assertInstanceOf(ReadRetryContext::class, $context);
        $this->assertTrue($context->fromAccountingSystem);
        $this->assertEquals($data, $context->data);
        $this->assertEquals(1, $context->errorId);
    }

    public function testRetryContextXero(): void
    {
        $data = ['test' => true];
        $error = new ReconciliationError(['id' => 1]);
        $error->integration_id = IntegrationType::Xero->value;
        $error->accounting_id = '1234';
        $error->retry_context = (object) $data;
        $context = $this->getFactory()->make($error);
        $this->assertInstanceOf(ReadRetryContext::class, $context);
        $this->assertTrue($context->fromAccountingSystem);
        $this->assertEquals($data, $context->data);
        $this->assertEquals(1, $context->errorId);
    }
}
