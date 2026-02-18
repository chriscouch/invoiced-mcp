<?php

namespace App\Tests\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\AccountingSync\Enums\SyncDirection;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\NetSuite\Writers\NetSuiteCustomerWriter;

class NetSuiteCustomerWriterTest extends AbstractWriterTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$customer->attention_to = 'Sherlock';
        self::$customer->phone = '1234567';
        self::$customer->saveOrFail();
    }

    public function testToArray(): void
    {
        $valueObject = new NetSuiteCustomerWriter(self::$customer);
        $response = $valueObject->toArray();
        $this->assertEquals([
            'id' => self::$customer->id,
            'companyname' => self::$customer->name,
            'accountnumber' => self::$customer->number,
            'currencysymbol' => 'usd',
            'zip' => self::$customer->postal_code,
            'email' => self::$customer->email,
            'entityid' => self::$customer->number,
            'active' => true,
            'type' => 'company',
            'addr1' => self::$customer->address1,
            'addr2' => self::$customer->address2,
            'city' => self::$customer->city,
            'state' => self::$customer->state,
            'country' => self::$customer->country,
            'attention_to' => self::$customer->attention_to,
            'phone' => self::$customer->phone,
            'netsuite_id' => null,
        ], $response);
    }

    public function testToArrayParent(): void
    {
        $customer = new Customer();
        $customer->active = false;
        $customer->name = 'Sherlock';
        $customer->email = 'sherlock2@example.com';
        $customer->parent_customer = self::$customer->id;
        $customer->saveOrFail();

        $valueObject = new NetSuiteCustomerWriter($customer);
        $response = $valueObject->toArray();
        $this->assertEquals([
            'netsuite_id' => null,
            'parent_customer' => [
                'id' => self::$customer->id,
                'companyname' => self::$customer->name,
                'accountnumber' => self::$customer->number,
                'entityid' => self::$customer->number,
                'netsuite_id' => null,
            ],
            'id' => $customer->id,
            'companyname' => $customer->name,
            'accountnumber' => $customer->number,
            'currencysymbol' => 'usd',
            'email' => $customer->email,
            'entityid' => $customer->number,
            'active' => null,
            'type' => 'company',
            'country' => $customer->country,
        ], $response);
    }

    public function testToArrayNetSuite(): void
    {
        self::hasNetSuiteCustomer('1');
        $customer1 = self::$customer;
        self::hasNetSuiteCustomer('2');
        self::$customer->parent_customer = $customer1->id;
        self::$customer->metadata = (object) [
            'category' => 'customer',
        ];
        self::$customer->avalara_exemption_number = 'tax_exempt';
        self::$customer->saveOrFail();

        (new AccountingSyncFieldMapping([
            'integration' => IntegrationType::NetSuite,
            'direction' => SyncDirection::Write,
            'object_type' => 'customer',
            'source_field' => 'metadata/category',
            'destination_field' => 'category',
            'data_type' => TransformFieldType::String,
            'enabled' => true,
        ]))->saveOrFail();

        (new AccountingSyncFieldMapping([
            'integration' => IntegrationType::NetSuite,
            'direction' => SyncDirection::Write,
            'object_type' => 'customer',
            'source_field' => TransformField::VALUE_ID,
            'value' => 'Invoiced',
            'destination_field' => 'subsidiary',
            'data_type' => TransformFieldType::String,
            'enabled' => true,
        ]))->saveOrFail();

        (new AccountingSyncFieldMapping([
            'integration' => IntegrationType::NetSuite,
            'direction' => SyncDirection::Write,
            'object_type' => 'customer',
            'source_field' => 'avalara_exemption_number',
            'destination_field' => 'tax_exempt',
            'data_type' => TransformFieldType::String,
            'enabled' => true,
        ]))->saveOrFail();

        (new AccountingSyncFieldMapping([
            'integration' => IntegrationType::NetSuite,
            'direction' => SyncDirection::Write,
            'object_type' => 'customer',
            'source_field' => 'tax_id',
            'destination_field' => 'tax_id',
            'data_type' => TransformFieldType::String,
            'enabled' => true,
        ]))->saveOrFail();

        $valueObject = new NetSuiteCustomerWriter(self::$customer);
        $response = $valueObject->toArray();
        $this->assertEquals([
            'netsuite_id' => 2,
            'parent_customer' => [
                'id' => $customer1->id,
                'companyname' => $customer1->name,
                'accountnumber' => $customer1->number,
                'entityid' => $customer1->number,
                'netsuite_id' => 1,
            ],
            'id' => self::$customer->id,
            'companyname' => self::$customer->name,
            'accountnumber' => self::$customer->number,
            'currencysymbol' => 'usd',
            'email' => self::$customer->email,
            'entityid' => self::$customer->number,
            'active' => true,
            'type' => 'company',
            'addr1' => self::$customer->address1,
            'addr2' => self::$customer->address2,
            'city' => self::$customer->city,
            'state' => self::$customer->state,
            'zip' => self::$customer->postal_code,
            'category' => 'customer',
            'subsidiary' => 'Invoiced',
            'country' => self::$customer->country,
            'tax_exempt' => 'tax_exempt',
        ], $response);
    }
}
