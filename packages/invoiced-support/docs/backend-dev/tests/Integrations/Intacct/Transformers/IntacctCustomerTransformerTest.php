<?php

namespace App\Tests\Integrations\Intacct\Transformers;

use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Transformers\IntacctCustomerTransformer;
use App\Tests\AppTestCase;
use SimpleXMLElement;

class IntacctCustomerTransformerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasIntacctAccount();
        self::$company->features->enable('multi_currency');

        $mapping = new AccountingSyncFieldMapping();
        $mapping->integration = IntegrationType::Intacct;
        $mapping->object_type = 'customer';
        $mapping->source_field = 'CUSTOMFIELD';
        $mapping->destination_field = 'metadata/custom_field';
        $mapping->data_type = TransformFieldType::String;
        $mapping->enabled = true;
        $mapping->save();
    }

    private function getTransformer(): IntacctCustomerTransformer
    {
        $syncProfile = new IntacctSyncProfile();
        $transformer = new IntacctCustomerTransformer();
        $transformer->initialize(self::$intacctAccount, $syncProfile);

        return $transformer;
    }

    public function testTransform(): void
    {
        $transformer = $this->getTransformer();

        $input = new SimpleXMLElement('<customer>
                    <NAME>Test</NAME>
                    <RECORDNO>1</RECORDNO>
                    <CUSTOMERID>CUST-0001</CUSTOMERID>
                    <CURRENCY>USD</CURRENCY>
                    <STATUS>active</STATUS>
                    <BILLTO.EMAIL1>test@example.com</BILLTO.EMAIL1>
                    <BILLTO.EMAIL2>tes2t@example.com</BILLTO.EMAIL2>
                    <BILLTO.MAILADDRESS.ADDRESS1>1234 Main St</BILLTO.MAILADDRESS.ADDRESS1>
                    <BILLTO.MAILADDRESS.ADDRESS2></BILLTO.MAILADDRESS.ADDRESS2>
                    <BILLTO.MAILADDRESS.CITY>Austin</BILLTO.MAILADDRESS.CITY>
                    <BILLTO.MAILADDRESS.STATE>TX</BILLTO.MAILADDRESS.STATE>
                    <BILLTO.MAILADDRESS.ZIP>123456</BILLTO.MAILADDRESS.ZIP>
                    <BILLTO.MAILADDRESS.COUNTRYCODE>US</BILLTO.MAILADDRESS.COUNTRYCODE>
                    <BILLTO.PHONE1>(123) 456-7890</BILLTO.PHONE1>
                    <DISPLAYCONTACT.EMAIL1></DISPLAYCONTACT.EMAIL1>
                    <DISPLAYCONTACT.EMAIL2></DISPLAYCONTACT.EMAIL2>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS1></DISPLAYCONTACT.MAILADDRESS.ADDRESS1>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS2></DISPLAYCONTACT.MAILADDRESS.ADDRESS2>
                    <DISPLAYCONTACT.MAILADDRESS.CITY></DISPLAYCONTACT.MAILADDRESS.CITY>
                    <DISPLAYCONTACT.MAILADDRESS.STATE></DISPLAYCONTACT.MAILADDRESS.STATE>
                    <DISPLAYCONTACT.MAILADDRESS.ZIP></DISPLAYCONTACT.MAILADDRESS.ZIP>
                    <DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE></DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE>
                    <DISPLAYCONTACT.PHONE1></DISPLAYCONTACT.PHONE1>
                    <TAXID></TAXID>
                    <CUSTOMFIELD>TEST</CUSTOMFIELD>
                </customer>');
        $this->assertEquals(new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '1',
            values: [
                'name' => 'Test',
                'active' => true,
                'number' => 'CUST-0001',
                'address1' => '1234 Main St',
                'address2' => '',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '123456',
                'country' => 'US',
                'phone' => '(123) 456-7890',
                'currency' => 'usd',
                'metadata' => [
                    'custom_field' => 'TEST',
                ],
            ],
            emails: ['test@example.com', 'tes2t@example.com'],
        ), $transformer->transform(new AccountingXmlRecord($input)));

        $input = new SimpleXMLElement('<customer>
                    <NAME>Test 2</NAME>
                    <RECORDNO>2</RECORDNO>
                    <CUSTOMERID>CUST-0002</CUSTOMERID>
                    <CURRENCY></CURRENCY>
                    <STATUS>active</STATUS>
                    <BILLTO.EMAIL1></BILLTO.EMAIL1>
                    <BILLTO.EMAIL2></BILLTO.EMAIL2>
                    <BILLTO.MAILADDRESS.ADDRESS1></BILLTO.MAILADDRESS.ADDRESS1>
                    <BILLTO.MAILADDRESS.ADDRESS2></BILLTO.MAILADDRESS.ADDRESS2>
                    <BILLTO.MAILADDRESS.CITY></BILLTO.MAILADDRESS.CITY>
                    <BILLTO.MAILADDRESS.STATE></BILLTO.MAILADDRESS.STATE>
                    <BILLTO.MAILADDRESS.ZIP></BILLTO.MAILADDRESS.ZIP>
                    <BILLTO.MAILADDRESS.COUNTRYCODE></BILLTO.MAILADDRESS.COUNTRYCODE>
                    <BILLTO.PHONE1></BILLTO.PHONE1>
                    <DISPLAYCONTACT.EMAIL1></DISPLAYCONTACT.EMAIL1>
                    <DISPLAYCONTACT.EMAIL2></DISPLAYCONTACT.EMAIL2>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS1></DISPLAYCONTACT.MAILADDRESS.ADDRESS1>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS2></DISPLAYCONTACT.MAILADDRESS.ADDRESS2>
                    <DISPLAYCONTACT.MAILADDRESS.CITY></DISPLAYCONTACT.MAILADDRESS.CITY>
                    <DISPLAYCONTACT.MAILADDRESS.STATE></DISPLAYCONTACT.MAILADDRESS.STATE>
                    <DISPLAYCONTACT.MAILADDRESS.ZIP></DISPLAYCONTACT.MAILADDRESS.ZIP>
                    <DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE></DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE>
                    <DISPLAYCONTACT.PHONE1></DISPLAYCONTACT.PHONE1>
                    <TAXID></TAXID>
                </customer>');
        $this->assertEquals(new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '2',
            values: [
                'name' => 'Test 2',
                'active' => true,
                'number' => 'CUST-0002',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
            ],
            emails: [],
        ), $transformer->transform(new AccountingXmlRecord($input)));

        $input = new SimpleXMLElement('<customer>
                    <NAME>After</NAME>
                    <RECORDNO>4</RECORDNO>
                    <CUSTOMERID></CUSTOMERID>
                    <CURRENCY></CURRENCY>
                    <STATUS>active</STATUS>
                    <BILLTO.EMAIL1></BILLTO.EMAIL1>
                    <BILLTO.EMAIL2></BILLTO.EMAIL2>
                    <BILLTO.MAILADDRESS.ADDRESS1></BILLTO.MAILADDRESS.ADDRESS1>
                    <BILLTO.MAILADDRESS.ADDRESS2></BILLTO.MAILADDRESS.ADDRESS2>
                    <BILLTO.MAILADDRESS.CITY></BILLTO.MAILADDRESS.CITY>
                    <BILLTO.MAILADDRESS.STATE></BILLTO.MAILADDRESS.STATE>
                    <BILLTO.MAILADDRESS.ZIP></BILLTO.MAILADDRESS.ZIP>
                    <BILLTO.MAILADDRESS.COUNTRYCODE></BILLTO.MAILADDRESS.COUNTRYCODE>
                    <BILLTO.PHONE1></BILLTO.PHONE1>
                    <DISPLAYCONTACT.EMAIL1>after@example.com</DISPLAYCONTACT.EMAIL1>
                    <DISPLAYCONTACT.EMAIL2></DISPLAYCONTACT.EMAIL2>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS1></DISPLAYCONTACT.MAILADDRESS.ADDRESS1>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS2></DISPLAYCONTACT.MAILADDRESS.ADDRESS2>
                    <DISPLAYCONTACT.MAILADDRESS.CITY></DISPLAYCONTACT.MAILADDRESS.CITY>
                    <DISPLAYCONTACT.MAILADDRESS.STATE></DISPLAYCONTACT.MAILADDRESS.STATE>
                    <DISPLAYCONTACT.MAILADDRESS.ZIP></DISPLAYCONTACT.MAILADDRESS.ZIP>
                    <DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE></DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE>
                    <DISPLAYCONTACT.PHONE1></DISPLAYCONTACT.PHONE1>
                    <TAXID></TAXID>
                </customer>');
        $this->assertEquals(new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '4',
            values: [
                'name' => 'After',
                'active' => true,
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
            ],
            emails: ['after@example.com'],
        ), $transformer->transform(new AccountingXmlRecord($input)));

        $input = new SimpleXMLElement('<customer>
                    <NAME>Inactive</NAME>
                    <RECORDNO>6</RECORDNO>
                    <CUSTOMERID></CUSTOMERID>
                    <STATUS>inactive</STATUS>
                    <BILLTO.EMAIL1></BILLTO.EMAIL1>
                    <BILLTO.EMAIL2></BILLTO.EMAIL2>
                    <BILLTO.MAILADDRESS.ADDRESS1></BILLTO.MAILADDRESS.ADDRESS1>
                    <BILLTO.MAILADDRESS.ADDRESS2></BILLTO.MAILADDRESS.ADDRESS2>
                    <BILLTO.MAILADDRESS.CITY></BILLTO.MAILADDRESS.CITY>
                    <BILLTO.MAILADDRESS.STATE></BILLTO.MAILADDRESS.STATE>
                    <BILLTO.MAILADDRESS.ZIP></BILLTO.MAILADDRESS.ZIP>
                    <BILLTO.MAILADDRESS.COUNTRYCODE></BILLTO.MAILADDRESS.COUNTRYCODE>
                    <BILLTO.PHONE1></BILLTO.PHONE1>
                    <DISPLAYCONTACT.EMAIL1></DISPLAYCONTACT.EMAIL1>
                    <DISPLAYCONTACT.EMAIL2></DISPLAYCONTACT.EMAIL2>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS1></DISPLAYCONTACT.MAILADDRESS.ADDRESS1>
                    <DISPLAYCONTACT.MAILADDRESS.ADDRESS2></DISPLAYCONTACT.MAILADDRESS.ADDRESS2>
                    <DISPLAYCONTACT.MAILADDRESS.CITY></DISPLAYCONTACT.MAILADDRESS.CITY>
                    <DISPLAYCONTACT.MAILADDRESS.STATE></DISPLAYCONTACT.MAILADDRESS.STATE>
                    <DISPLAYCONTACT.MAILADDRESS.ZIP></DISPLAYCONTACT.MAILADDRESS.ZIP>
                    <DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE></DISPLAYCONTACT.MAILADDRESS.COUNTRYCODE>
                    <DISPLAYCONTACT.PHONE1></DISPLAYCONTACT.PHONE1>
                    <TAXID></TAXID>
                </customer>');
        $this->assertEquals(new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '6',
            values: [
                'name' => 'Inactive',
                'active' => false,
                'address1' => '',
                'address2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
            ],
            emails: [],
        ), $transformer->transform(new AccountingXmlRecord($input)));
    }
}
