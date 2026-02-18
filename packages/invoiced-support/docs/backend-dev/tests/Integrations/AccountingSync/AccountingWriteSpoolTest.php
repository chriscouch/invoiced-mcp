<?php

namespace App\Tests\Integrations\AccountingSync;

use App\AccountsReceivable\Models\Customer;
use App\Tests\AppTestCase;
use App\Core\Orm\Event\ModelCreated;

class AccountingWriteSpoolTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasQuickBooksAccount();
    }

    public function testAccountingWriteSpool(): void
    {
        $spool = self::getService('test.accounting_write_spool');
        $this->assertEquals(0, $spool->size());

        /**
         * Test enqueue from create event.
         */
        $customer = new Customer();
        $customer->name = 'Sherlock';
        $customer->email = 'sherlock@example.com';
        $customer->address1 = 'Test';
        $customer->address2 = 'Address';
        $customer->saveOrFail();

        [$model, $eventName] = $spool->peek();
        $this->assertEquals(1, $spool->size());
        $this->assertEquals($customer->id(), $model->id());
        $this->assertEquals(ModelCreated::getName(), $eventName);
    }
}
