<?php

namespace App\Tests\Integrations\AccountingSync;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\AccountingMappingFactory;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Tests\AppTestCase;
use Exception;

class AccountingModelsFactoryTest extends AppTestCase
{
    public function testGetInstance(): void
    {
        $factory = new AccountingMappingFactory();
        $this->assertInstanceOf(AccountingCreditNoteMapping::class, $factory->getInstance(new CreditNote()));
        $this->assertInstanceOf(AccountingCustomerMapping::class, $factory->getInstance(new Customer()));
        $this->assertInstanceOf(AccountingInvoiceMapping::class, $factory->getInstance(new Invoice()));
        $this->assertInstanceOf(AccountingPaymentMapping::class, $factory->getInstance(new Payment()));
        $this->assertInstanceOf(AccountingTransactionMapping::class, $factory->getInstance(new Transaction()));
        $this->assertNull($factory->getInstance(new class() extends AccountingWritableModel {
            public function getAccountingObjectReference(): InvoicedObjectReference
            {
                throw new Exception('not implemented');
            }
        }));
    }
}
