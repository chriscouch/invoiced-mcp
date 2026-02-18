<?php

namespace App\Tests\Integrations\AccountingSync\Traits;

use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;
use App\Core\Orm\Model;

class AccountingApiParametersTraitTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCreditNote();
        self::hasPayment();
        self::hasInvoice();
        self::hasTransaction();
    }

    public function testCreateAccountingMapping(): void
    {
        $object = new class() {
            use AccountingApiParametersTrait;

            protected Model $model;

            public function setAccounting(): void
            {
                $this->accountingId = '1';
                $this->accountingSystem = IntegrationType::Intacct;
            }

            public function testCreateMapping(AccountingWritableModel $model): void
            {
                $this->createAccountingMapping($model);
            }
        };

        foreach ([
                     AccountingCustomerMapping::class => self::$customer,
                     AccountingInvoiceMapping::class => self::$invoice,
                     AccountingCreditNoteMapping::class => self::$creditNote,
                     AccountingPaymentMapping::class => self::$payment,
                     AccountingTransactionMapping::class => self::$transaction,
        ] as $mappingClass => $model) {
            $object->testCreateMapping($model);
        }
        $this->assertEquals(0, $mappingClass::count());

        $object->setAccounting();

        $object->testCreateMapping($model);
        $this->assertEquals(1, $mappingClass::count());

        $object->testCreateMapping($model);
        $this->assertEquals(1, $mappingClass::count());
    }
}
