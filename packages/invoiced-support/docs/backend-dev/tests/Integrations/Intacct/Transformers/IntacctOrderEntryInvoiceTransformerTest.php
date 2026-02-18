<?php

namespace App\Tests\Integrations\Intacct\Transformers;

use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Transformers\IntacctOrderEntryInvoiceTransformer;
use App\Tests\AppTestCase;
use SimpleXMLElement;

class IntacctOrderEntryInvoiceTransformerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasIntacctAccount();
    }

    /**
     * INVD-3082.
     */
    public function testTransformTaxes(): void
    {
        $transformer = $this->getTransformer(['read_invoices_as_drafts' => false]);
        $transaction1 = new SimpleXMLElement(
            '<sodocumentsubtotals>
                <PRRECORDKEY>test</PRRECORDKEY>
                <RECORDNO>test</RECORDNO>
                <SUBTOTALS>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>DESCRIPTION</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>8.5</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                </SUBTOTALS>
            </sodocumentsubtotals>'
        );
        $subtotalsInvoice = new AccountingXmlRecord($transaction1);
        $transformer->setDocumentType('Sales Invoice');
        /** @var AccountingInvoice $result */
        $result = $transformer->transform($subtotalsInvoice);
        $this->assertEquals(8.5, $result->values['tax']);
        $this->assertEquals(-1, $result->values['discount']);

        $transaction2 = new SimpleXMLElement(
            '<sodocumentsubtotals>
                <PRRECORDKEY>test</PRRECORDKEY>
                <RECORDNO>test</RECORDNO>
                <SUBTOTALS>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>DESCRIPTION</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>0</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>0</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                </SUBTOTALS>
            </sodocumentsubtotals>'
        );
        $subtotalsInvoice = new AccountingXmlRecord($transaction2);
        $transformer->setDocumentType('Sales Invoice');
        /** @var AccountingInvoice $result */
        $result = $transformer->transform($subtotalsInvoice);
        $this->assertEquals(0, $result->values['tax']);
        $this->assertEquals(0, $result->values['discount']);

        $transaction3 = new SimpleXMLElement(
            '<sodocumentsubtotals>
                <PRRECORDKEY>test</PRRECORDKEY>
                <RECORDNO>test</RECORDNO>
                <SUBTOTALS>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>DESCRIPTION</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>-1</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>-1</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                </SUBTOTALS>
            </sodocumentsubtotals>'
        );
        $subtotalsInvoice = new AccountingXmlRecord($transaction3);
        $transformer->setDocumentType('Sales Invoice');
        /** @var AccountingInvoice $result */
        $result = $transformer->transform($subtotalsInvoice);
        $this->assertEquals(-1, $result->values['tax']);
        $this->assertEquals(1, $result->values['discount']);

        $transaction4 = new SimpleXMLElement(
            '<sodocumentsubtotals>
                <PRRECORDKEY>test</PRRECORDKEY>
                <RECORDNO>test</RECORDNO>
                <SUBTOTALS>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>DESCRIPTION</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL></TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL></TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                </SUBTOTALS>
            </sodocumentsubtotals>'
        );
        $subtotalsInvoice = new AccountingXmlRecord($transaction4);
        $transformer->setDocumentType('Sales Invoice');
        /** @var AccountingInvoice $result */
        $result = $transformer->transform($subtotalsInvoice);
        $this->assertEquals(0, $result->values['tax']);
        $this->assertEquals(0, $result->values['discount']);

        $transaction5 = new SimpleXMLElement(
            '<sodocumentsubtotals>
                <PRRECORDKEY>test</PRRECORDKEY>
                <RECORDNO>test</RECORDNO>
                <MEGAENTITYID>111</MEGAENTITYID>
                <SUBTOTALS>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>DESCRIPTION</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>2</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>0</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>3</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>-1</TOTAL>
                        <DESCRIPTION>sales tax</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>-1</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>-2</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>0</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>-3</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                    <sodocumentsubtotals>
                        <RECORDNO>1</RECORDNO>
                        <TOTAL>1</TOTAL>
                        <DESCRIPTION>discount</DESCRIPTION>
                    </sodocumentsubtotals>
                </SUBTOTALS>
            </sodocumentsubtotals>'
        );
        $subtotalsInvoice = new AccountingXmlRecord($transaction5);
        $transformer->setDocumentType('Sales Invoice');
        /** @var AccountingInvoice $result */
        $result = $transformer->transform($subtotalsInvoice);
        $this->assertEquals(5, $result->values['tax']);
        $this->assertEquals(5, $result->values['discount']);
        $this->assertEquals(111, $result->values['metadata']['intacct_entity']);
    }

    /**
     * @dataProvider transformProvider
     */
    public function testTransform(AccountingXmlRecord $record, array $params, AccountingInvoice $expected): void
    {
        $transformer = $this->getTransformer($params);
        $result = $transformer->transform($record);
        $this->assertEquals($expected, $result);
    }

    public function transformProvider(): array
    {
        return [
            [
                new AccountingXmlRecord(
                    new SimpleXMLElement(
                        '<sodocument>
                    <RECORDNO>456</RECORDNO>
                    <PRRECORDKEY>4567</PRRECORDKEY>
                    <DOCNO>INV-1012</DOCNO>
                    <CURRENCY>USD</CURRENCY>
                    <WHENPOSTED>2017-01-12</WHENPOSTED>
                    <WHENDUE>2017-02-11</WHENDUE>
                    <TRX_TOTALPAID>2.51</TRX_TOTALPAID>
                    <TERM>
                        <NAME>NET 30</NAME>
                    </TERM>
                    <MESSAGE>Testing</MESSAGE>
                    <PONUMBER>PO-123456789012345678901234567890</PONUMBER>
                    <CONTRACTID>483</CONTRACTID>
                    <CUSTREC>2</CUSTREC>
                    <CUSTVENDID>CUST-00001</CUSTVENDID>
                    <CUSTVENDNAME>Test Customer</CUSTVENDNAME>
                    <SHIPTO>
                        <PRINTAS>Bojangle Jones</PRINTAS>
                        <MAILADDRESS>
                            <ADDRESS1>1234 Main St</ADDRESS1>
                            <ADDRESS2></ADDRESS2>
                            <CITY>Austin</CITY>
                            <STATE>TX</STATE>
                            <ZIP>78701</ZIP>
                            <COUNTRYCODE>US</COUNTRYCODE>
                        </MAILADDRESS>
                    </SHIPTO>
                    <SODOCUMENTENTRIES>
                        <sodocumententry>
                            <ITEMDESC>Marketing guides: </ITEMDESC>
                            <QUANTITY>5.5</QUANTITY>
                            <UIPRICE>72</UIPRICE>
                            <UIVALUE>396</UIVALUE>
                            <TRX_PRICE>144</TRX_PRICE>
                            <TRX_VALUE>792</TRX_VALUE>
                            <MULTIPLIER>1</MULTIPLIER>
                        </sodocumententry>
                        <sodocumententry>
                            <ITEMDESC>Contract discount test</ITEMDESC>
                            <QUANTITY>10.1234</QUANTITY>
                            <UIPRICE>72</UIPRICE>
                            <UIVALUE>721.31</UIVALUE>
                            <TRX_PRICE>144</TRX_PRICE>
                            <TRX_VALUE>1442.62</TRX_VALUE>
                            <MULTIPLIER>2</MULTIPLIER>
                        </sodocumententry>
                    </SODOCUMENTENTRIES>
                    <SUBTOTALS>
                        <sodocumentsubtotals>
                            <RECORDNO>1</RECORDNO>
                            <DESCRIPTION>Sales Tax</DESCRIPTION>
                            <TOTAL>20</TOTAL>
                            <TRX_TOTAL>40</TRX_TOTAL>
                        </sodocumentsubtotals>
                    </SUBTOTALS>
                </sodocument>'
                    )
                ),
                ['read_invoices_as_drafts' => false],
                new AccountingInvoice(
                    integration: IntegrationType::Intacct,
                    accountingId: '4567',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::Intacct,
                        accountingId: '2',
                        values: ['name' => 'Test Customer', 'number' => 'CUST-00001'],
                    ),
                    values: [
                        'currency' => 'usd',
                        'calculate_taxes' => false,
                        'number' => 'INV-1012',
                        'date' => mktime(6, 0, 0, 1, 12, 2017),
                        'due_date' => mktime(18, 0, 0, 2, 11, 2017),
                        'purchase_order' => 'PO-12345678901234567890123456789',
                        'payment_terms' => 'NET 30',
                        'items' => [
                            [
                                'name' => 'Marketing guides',
                                'quantity' => 5.5,
                                'unit_cost' => 72.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Contract discount test',
                                'quantity' => 1,
                                'unit_cost' => 721.31,
                                'metadata' => [
                                    'intacct_quantity' => 20.2468,
                                ],
                            ],
                        ],
                        'tax' => 20.0,
                        'notes' => 'Testing',
                        'metadata' => [
                            'intacct_document_type' => 'Sales Invoice',
                            'intacct_purchase_order' => 'PO-123456789012345678901234567890',
                            'intacct_contract_id' => 483,
                        ],
                        'ship_to' => [
                            'name' => 'Bojangle Jones',
                            'address1' => '1234 Main St',
                            'address2' => '',
                            'city' => 'Austin',
                            'state' => 'TX',
                            'postal_code' => '78701',
                            'country' => 'US',
                        ],
                        'discount' => 0,
                    ],
                ),
            ],
            [
                new AccountingXmlRecord(
                    new SimpleXMLElement(
                        '<sodocument>
                    <RECORDNO>456</RECORDNO>
                    <PRRECORDKEY>4567</PRRECORDKEY>
                    <DOCNO>INV-1012</DOCNO>
                    <CURRENCY>USD</CURRENCY>
                    <WHENPOSTED>2017-01-12</WHENPOSTED>
                    <WHENDUE>2017-02-11</WHENDUE>
                    <TRX_TOTALPAID>2.51</TRX_TOTALPAID>
                    <TERM>
                        <NAME>NET 30</NAME>
                    </TERM>
                    <MESSAGE>Testing</MESSAGE>
                    <PONUMBER>PO-123456789012345678901234567890</PONUMBER>
                    <CONTRACTID>483</CONTRACTID>
                    <CUSTREC>2</CUSTREC>
                    <CUSTVENDID>CUST-00001</CUSTVENDID>
                    <CUSTVENDNAME>Test Customer</CUSTVENDNAME>
                    <SHIPTO>
                        <PRINTAS>Bojangle Jones</PRINTAS>
                        <MAILADDRESS>
                            <ADDRESS1>1234 Main St</ADDRESS1>
                            <ADDRESS2></ADDRESS2>
                            <CITY>Austin</CITY>
                            <STATE>TX</STATE>
                            <ZIP>78701</ZIP>
                            <COUNTRYCODE>US</COUNTRYCODE>
                        </MAILADDRESS>
                    </SHIPTO>
                    <SODOCUMENTENTRIES>
                        <sodocumententry>
                            <ITEMDESC>Marketing guides: </ITEMDESC>
                            <QUANTITY>5.5</QUANTITY>
                            <UIPRICE>72</UIPRICE>
                            <UIVALUE>396</UIVALUE>
                            <TRX_PRICE>144</TRX_PRICE>
                            <TRX_VALUE>792</TRX_VALUE>
                            <MULTIPLIER>1</MULTIPLIER>
                        </sodocumententry>
                        <sodocumententry>
                            <ITEMDESC>Contract discount test</ITEMDESC>
                            <QUANTITY>10.1234</QUANTITY>
                            <UIPRICE>72</UIPRICE>
                            <UIVALUE>721.31</UIVALUE>
                            <TRX_PRICE>144</TRX_PRICE>
                            <TRX_VALUE>1442.62</TRX_VALUE>
                            <MULTIPLIER>2</MULTIPLIER>
                        </sodocumententry>
                    </SODOCUMENTENTRIES>
                    <SUBTOTALS>
                        <sodocumentsubtotals>
                            <RECORDNO>1</RECORDNO>
                            <DESCRIPTION>Sales Tax</DESCRIPTION>
                            <TOTAL>20</TOTAL>
                            <TRX_TOTAL>40</TRX_TOTAL>
                        </sodocumentsubtotals>
                    </SUBTOTALS>
                </sodocument>'
                    )
                ),
                [
                    'read_invoices_as_drafts' => false,
                    'multi_currency' => true,
                ],
                new AccountingInvoice(
                    integration: IntegrationType::Intacct,
                    accountingId: '4567',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::Intacct,
                        accountingId: '2',
                        values: ['name' => 'Test Customer', 'number' => 'CUST-00001'],
                    ),
                    values: [
                        'currency' => 'usd',
                        'calculate_taxes' => false,
                        'number' => 'INV-1012',
                        'date' => mktime(6, 0, 0, 1, 12, 2017),
                        'due_date' => mktime(18, 0, 0, 2, 11, 2017),
                        'payment_terms' => 'NET 30',
                        'purchase_order' => 'PO-12345678901234567890123456789',
                        'items' => [
                            [
                                'name' => 'Marketing guides',
                                'quantity' => 5.5,
                                'unit_cost' => 144.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Contract discount test',
                                'quantity' => 1,
                                'unit_cost' => 1442.62,
                                'metadata' => [
                                    'intacct_quantity' => 20.2468,
                                ],
                            ],
                        ],
                        'tax' => 40.0,
                        'notes' => 'Testing',
                        'metadata' => [
                            'intacct_document_type' => 'Sales Invoice',
                            'intacct_purchase_order' => 'PO-123456789012345678901234567890',
                            'intacct_contract_id' => 483,
                        ],
                        'ship_to' => [
                            'name' => 'Bojangle Jones',
                            'address1' => '1234 Main St',
                            'address2' => '',
                            'city' => 'Austin',
                            'state' => 'TX',
                            'postal_code' => '78701',
                            'country' => 'US',
                        ],
                        'discount' => 0,
                    ],
                ),
            ],
            [
                new AccountingXmlRecord(
                    new SimpleXMLElement(
                        '<sodocument>
                    <RECORDNO>456</RECORDNO>
                    <PRRECORDKEY>4567</PRRECORDKEY>
                    <DOCNO>INV-1012</DOCNO>
                    <CURRENCY>USD</CURRENCY>
                    <WHENPOSTED>2017-01-12</WHENPOSTED>
                    <WHENDUE>2017-02-11</WHENDUE>
                    <TRX_TOTALPAID>2.51</TRX_TOTALPAID>
                    <TERM>
                        <NAME>NET 30</NAME>
                    </TERM>
                    <MESSAGE>Testing</MESSAGE>
                    <PONUMBER>PO-123456789012345678901234567890</PONUMBER>
                    <CONTRACTID>483</CONTRACTID>
                    <CUSTREC>2</CUSTREC>
                    <CUSTVENDID>CUST-00001</CUSTVENDID>
                    <CUSTVENDNAME>Test Customer</CUSTVENDNAME>
                    <SHIPTO>
                        <PRINTAS>Bojangle Jones</PRINTAS>
                        <MAILADDRESS>
                            <ADDRESS1>1234 Main St</ADDRESS1>
                            <ADDRESS2></ADDRESS2>
                            <CITY>Austin</CITY>
                            <STATE>TX</STATE>
                            <ZIP>78701</ZIP>
                            <COUNTRYCODE>US</COUNTRYCODE>
                        </MAILADDRESS>
                    </SHIPTO>
                    <BILLTOKEY>678</BILLTOKEY>
                    <BILLTO>
                        <PRINTAS>BillTo Customer</PRINTAS>
                        <EMAIL1>test@example.com</EMAIL1>
                        <EMAIL2>test2@example.com</EMAIL2>
                        <PHONE1>1234-6789</PHONE1>
                        <MAILADDRESS>
                            <ADDRESS1>1234 Main St</ADDRESS1>
                            <ADDRESS2></ADDRESS2>
                            <CITY>Austin</CITY>
                            <STATE>TX</STATE>
                            <ZIP>78701</ZIP>
                            <COUNTRYCODE>US</COUNTRYCODE>
                        </MAILADDRESS>
                    </BILLTO>
                    <SODOCUMENTENTRIES>
                        <sodocumententry>
                            <ITEMDESC>Marketing guides: </ITEMDESC>
                            <QUANTITY>5.5</QUANTITY>
                            <UIPRICE>72</UIPRICE>
                            <UIVALUE>396</UIVALUE>
                            <TRX_PRICE>144</TRX_PRICE>
                            <TRX_VALUE>792</TRX_VALUE>
                            <MULTIPLIER>1</MULTIPLIER>
                        </sodocumententry>
                        <sodocumententry>
                            <ITEMDESC>Contract discount test</ITEMDESC>
                            <QUANTITY>10.1234</QUANTITY>
                            <UIPRICE>72</UIPRICE>
                            <UIVALUE>721.31</UIVALUE>
                            <TRX_PRICE>144</TRX_PRICE>
                            <TRX_VALUE>1442.62</TRX_VALUE>
                            <MULTIPLIER>2</MULTIPLIER>
                        </sodocumententry>
                    </SODOCUMENTENTRIES>
                    <SUBTOTALS>
                        <sodocumentsubtotals>
                            <RECORDNO>1</RECORDNO>
                            <DESCRIPTION>Sales Tax</DESCRIPTION>
                            <TOTAL>20</TOTAL>
                            <TRX_TOTAL>40</TRX_TOTAL>
                        </sodocumentsubtotals>
                    </SUBTOTALS>
                </sodocument>'
                    )
                ),
                [
                    'read_invoices_as_drafts' => false,
                    'customer_import_type' => IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO,
                ],
                new AccountingInvoice(
                    integration: IntegrationType::Intacct,
                    accountingId: '4567',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::Intacct,
                        accountingId: '678',
                        values: [
                            'name' => 'BillTo Customer',
                            'address1' => '1234 Main St',
                            'address2' => '',
                            'city' => 'Austin',
                            'state' => 'TX',
                            'postal_code' => '78701',
                            'phone' => '1234-6789',
                            'country' => 'US',
                            'metadata' => [
                                'intacct_customer_number' => 'CUST-00001',
                            ],
                        ],
                        emails: [
                            'test@example.com',
                            'test2@example.com',
                        ],
                    ),
                    values: [
                        'currency' => 'usd',
                        'calculate_taxes' => false,
                        'number' => 'INV-1012',
                        'date' => mktime(6, 0, 0, 1, 12, 2017),
                        'due_date' => mktime(18, 0, 0, 2, 11, 2017),
                        'payment_terms' => 'NET 30',
                        'purchase_order' => 'PO-12345678901234567890123456789',
                        'items' => [
                            [
                                'name' => 'Marketing guides',
                                'quantity' => 5.5,
                                'unit_cost' => 72.0,
                                'metadata' => [],
                            ],
                            [
                                'name' => 'Contract discount test',
                                'quantity' => 1,
                                'unit_cost' => 721.31,
                                'metadata' => [
                                    'intacct_quantity' => 20.2468,
                                ],
                            ],
                        ],
                        'tax' => 20.0,
                        'notes' => 'Testing',
                        'metadata' => [
                            'intacct_document_type' => 'Sales Invoice',
                            'intacct_purchase_order' => 'PO-123456789012345678901234567890',
                            'intacct_contract_id' => 483,
                        ],
                        'ship_to' => [
                            'name' => 'Bojangle Jones',
                            'address1' => '1234 Main St',
                            'address2' => '',
                            'city' => 'Austin',
                            'state' => 'TX',
                            'postal_code' => '78701',
                            'country' => 'US',
                        ],
                        'discount' => 0,
                    ],
                ),
            ],
        ];
    }

    private function getTransformer(array $params): IntacctOrderEntryInvoiceTransformer
    {
        if ($params['multi_currency'] ?? false) {
            self::$company->features->enable('multi_currency');
        } else {
            self::$company->features->disable('multi_currency');
        }

        $transformer = new IntacctOrderEntryInvoiceTransformer(self::getService('test.tenant'));
        $transformer->setDocumentType('Sales Invoice');
        $transformer->initialize(self::$intacctAccount, new IntacctSyncProfile($params));

        return $transformer;
    }
}
