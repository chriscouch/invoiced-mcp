<?php

namespace App\Tests\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Item;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use App\Integrations\NetSuite\Writers\NetSuiteCreditNoteWriter;
use App\Integrations\NetSuite\Writers\NetSuiteInvoiceWriter;
use App\PaymentProcessing\Exceptions\ReconciliationException;

class NetSuiteCreditNoteWriterTest extends AbstractWriterTestCase
{
    public static AccountingSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasNetSuiteCreditNote();

        self::$syncProfile = new AccountingSyncProfile();
        self::$syncProfile->integration = IntegrationType::NetSuite;
        self::$syncProfile->parameters = (object) [
            'fallback_item_id' => '101',
        ];
        self::$syncProfile->saveOrFail();
    }

    public function testNoLineItemFallback(): void
    {
        $profile = new AccountingSyncProfile();
        $valueObject = new NetSuiteInvoiceWriter(self::$creditNote, $profile);
        try {
            $valueObject->toArray();
            $this->assertFalse(true, 'Should have thrown an exception');
        } catch (ReconciliationException $e) {
            $this->assertEquals('No fallback item id set for the profile', $e->getMessage());
        }
    }

    public function testToArray(): void
    {
        $model = new CreditNote();
        $model->setCustomer(self::$customer);

        $item = new Item();
        $item->name = 'Test Item2';
        $item->id = 'test-item2';
        $item->description = 'Description';
        $item->unit_cost = 1000;
        $item->saveOrFail();
        self::hasItem();

        self::getService('test.database')->insert('AccountingItemMappings', [
            'tenant_id' => self::$company->id,
            'item_id' => $item->internal_id,
            'integration_id' => IntegrationType::NetSuite->value,
            'accounting_id' => '102',
            'source' => AbstractMapping::SOURCE_INVOICED,
        ]);

        $model->items = [
            [
                'quantity' => 1,
                'unit_cost' => 1000,
                'catalog_item_id' => self::$item->internal_id,
            ],
            [
                'quantity' => 2,
                'unit_cost' => 2000,
                'catalog_item_id' => $item->internal_id,
            ],
        ];
        $model->saveOrFail();

        $valueObject = new NetSuiteCreditNoteWriter($model, self::$syncProfile);

        $response = $valueObject->toArray();

        $this->assertEquals([
            'netsuite_id' => null,
            'parent_customer' => [
                'id' => self::$customer->id,
                'companyname' => self::$customer->name,
                'accountnumber' => self::$customer->number,
                'netsuite_id' => null,
                'entityid' => self::$customer->number,
            ],
            'id' => $model->id,
            'status' => $model->status,
            'items' => [
                [
                    'quantity' => 1,
                    'rate' => 1000,
                    'item' => 101,
                ],
                [
                    'quantity' => 2,
                    'rate' => 2000,
                    'item' => 102,
                ],
            ],
            'currencysymbol' => $model->currency,
            'tranid' => $model->number,
            'trandate' => $model->date,
            'discountrate' => 0,
            'taxrate' => 0,
            'discountitem' => null,
            'taxlineitem' => null,
            'location' => null,
        ], $response);
    }

    public function testToArrayNetSuite(): void
    {
        self::$syncProfile->parameters = (object) [
            'fallback_item_id' => '101',
            'location' => '201',
        ];
        self::$syncProfile->saveOrFail();

        $mapping = new AccountingCustomerMapping();
        $mapping->customer = self::$customer;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = '1';
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();
        self::hasCoupon();
        self::hasTaxRate();

        $coupon2 = new Coupon();
        $coupon2->id = 'coupon2';
        $coupon2->name = 'Coupon2';
        $coupon2->is_percent = false;
        $coupon2->value = 10;
        $coupon2->saveOrFail();

        $model = self::$creditNote;
        $model->notes = 'test';
        $model->taxes = [
            'tax_rate' => self::$taxRate->id,
        ];
        $model->discounts = [
            'coupon1' => self::$coupon->id,
            'coupon2' => $coupon2->id,
        ];
        $model->saveOrFail();

        $model = self::$creditNote;
        $valueObject = new NetSuiteCreditNoteWriter($model, self::$syncProfile);

        try {
            $valueObject->toArray();
        } catch (NetSuiteReconciliationException $e) {
            $this->assertEquals('Discount item is required for discount rate', $e->getMessage());
        }

        self::$syncProfile->parameters->discountitem = '1001';
        self::$syncProfile->saveOrFail();

        try {
            $valueObject->toArray();
        } catch (NetSuiteReconciliationException $e) {
            $this->assertEquals('Tax item is required for tax rate', $e->getMessage());
        }

        self::$syncProfile->parameters->taxlineitem = '1002';
        self::$syncProfile->saveOrFail();
        $response = $valueObject->toArray();
        $this->assertEquals([
            'netsuite_id' => '4',
            'parent_customer' => [
                'id' => self::$customer->id,
                'companyname' => self::$customer->name,
                'accountnumber' => self::$customer->number,
                'entityid' => self::$customer->number,
                'netsuite_id' => '1',
            ],
            'id' => $model->id,
            'memo' => 'test',
            'status' => $model->status,
            'discountrate' => -15,
            'taxrate' => 4.25,
            'items' => [
                [
                    'quantity' => 1,
                    'rate' => 100,
                    'item' => '101',
                ],
            ],
            'currencysymbol' => $model->currency,
            'tranid' => $model->number,
            'trandate' => $model->date,
            'discountitem' => 1001,
            'taxlineitem' => 1002,
            'location' => 201,
        ], $response);
    }
}
