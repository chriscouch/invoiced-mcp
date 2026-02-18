<?php

namespace App\Tests\Integrations\Xero\Writers;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Writers\XeroCustomerWriter;
use App\Integrations\Xero\Writers\XeroInvoiceWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroInvoiceWriterTest extends AppTestCase
{
    private static StatsdClient $statsd;
    private static XeroSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->date = 1640044800;
        self::$invoice->saveOrFail();
        self::$company->time_zone = 'America/Los_Angeles';
        self::$company->saveOrFail();
        self::$company->useTimezone();

        self::hasXeroAccount();
        self::$syncProfile = new XeroSyncProfile();
        self::$syncProfile->item_account = 'SALES001';
        self::$syncProfile->write_customers = true;
        self::$syncProfile->write_invoices = true;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();

        self::$statsd = new StatsdClient();
    }

    private function getWriter(XeroApi $xeroApi): XeroInvoiceWriter
    {
        $customerWriter = new XeroCustomerWriter($xeroApi);
        $writer = new XeroInvoiceWriter($xeroApi, $customerWriter, 'https://app.invoiced.com');
        $writer->setStatsd(self::$statsd);

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $writer = $this->getWriter($xeroApi);
        $this->assertFalse($writer->isEnabled(new XeroSyncProfile(['write_invoices' => false])));
        $this->assertTrue($writer->isEnabled(new XeroSyncProfile(['write_invoices' => true])));
    }

    public function testCreate(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Invoices', [
                    'where' => 'Type=="ACCREC" AND InvoiceNumber=="INV-00001"',
                ],
            ])
            ->andReturn([]);
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Contacts', [
                    'where' => 'Name=="Sherlock"',
                    'includeArchived' => 'true',
                ],
            ])
            ->andReturn([]);
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Contacts', [
                    'where' => 'AccountNumber=="CUST-00001"',
                    'includeArchived' => 'true',
                ],
            ])
            ->andReturn([]);
        $xeroApi->shouldReceive('createOrUpdate')
            ->withArgs([
                'Contacts',
                [
                    'Name' => 'Sherlock',
                    'ContactNumber' => 'CUST-00001',
                    'AccountNumber' => 'CUST-00001',
                    'ContactStatus' => 'ACTIVE',
                    'EmailAddress' => 'sherlock@example.com',
                    'Addresses' => [
                        [
                            'AddressLine1' => 'Test',
                            'AddressLine2' => 'Address',
                            'City' => 'Austin',
                            'Country' => 'US',
                            'PostalCode' => '78701',
                            'Region' => 'TX',
                            'AddressType' => 'STREET',
                        ],
                    ],
                ],
            ])
            ->andReturn((object) ['ContactID' => 'testCreate']);
        $xeroApi->shouldReceive('createOrUpdate')
            ->withArgs([
                'Invoices',
                [
                    'Status' => 'AUTHORISED',
                    'Contact' => [
                        'ContactID' => 'testCreate',
                    ],
                    'CurrencyCode' => 'USD',
                    'Reference' => null,
                    'Date' => '2021-12-20',
                    'LineItems' => [
                        [
                            'Description' => 'Test Item test',
                            'AccountCode' => 'SALES001',
                            'Quantity' => 1.0,
                            'UnitAmount' => 100.0,
                            'LineAmount' => 100.0,
                        ],
                    ],
                    'LineAmountTypes' => 'NoTax',
                    'Type' => 'ACCREC',
                    'InvoiceNumber' => 'INV-00001',
                    'Url' => 'https://app.invoiced.com/invoices/'.self::$invoice->id(),
                    'DueDate' => '2021-12-20',
                ],
            ])
            ->andReturn((object) ['InvoiceID' => '1234']);

        $writer = $this->getWriter($xeroApi);

        $writer->create(self::$invoice, self::$xeroAccount, self::$syncProfile);

        /** @var AccountingInvoiceMapping $mapping */
        $mapping = AccountingInvoiceMapping::find(self::$invoice->id());
        $this->assertEquals(IntegrationType::Xero->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testVoid(): void
    {
        self::$invoice->void();

        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $xeroApi->shouldReceive('createOrUpdate')
            ->withArgs([
                'Invoices', [
                    'Status' => 'VOIDED',
                    'InvoiceID' => '1234',
                ],
            ])
            ->andReturn()
            ->once();

        $writer = $this->getWriter($xeroApi);

        $writer->update(self::$invoice, self::$xeroAccount, self::$syncProfile);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCreateDimensionMapping(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->date = 1640044800;
        $invoice->due_date = 1642636800;
        $invoice->metadata = (object) [
            'xero_lineamounttypes' => 'TaxInclusive',
        ];
        $invoice->items = [
            [
                'unit_cost' => 100,
                'name' => 'Test',
                'metadata' => (object) [
                    'xero_accountcode' => 'glaccountno',
                    'xero_itemcode' => 'item',
                    'xero_taxtype' => 'tax',
                    'xero_trackingname1' => 'tracking1',
                    'xero_trackingoption1' => 'option1',
                    'xero_trackingname2' => 'tracking1',
                    'xero_trackingoption2' => 'option2',
                ],
            ],
            [
                'unit_cost' => 100,
                'name' => 'Test',
            ],
        ];
        $invoice->saveOrFail();

        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Invoices', [
                    'where' => 'Type=="ACCREC" AND InvoiceNumber=="INV-00002"',
                ],
            ])
            ->andReturn([]);
        $xeroApi->shouldReceive('createOrUpdate')
            ->withArgs([
                'Invoices',
                [
                    'Status' => 'AUTHORISED',
                    'Contact' => [
                        'ContactID' => 'testCreate',
                    ],
                    'CurrencyCode' => 'USD',
                    'Reference' => null,
                    'Date' => '2021-12-20',
                    'LineItems' => [
                        [
                            'Description' => 'Test',
                            'AccountCode' => 'glaccountno',
                            'Quantity' => 1.0,
                            'UnitAmount' => 100.0,
                            'LineAmount' => 100.0,
                            'ItemCode' => 'item',
                            'TaxType' => 'tax',
                            'Tracking' => [
                                [
                                    'Name' => 'tracking1',
                                    'Option' => 'option1',
                                ],
                                [
                                    'Name' => 'tracking1',
                                    'Option' => 'option2',
                                ],
                            ],
                        ],
                        [
                            'Description' => 'Test',
                            'AccountCode' => 'SALES001',
                            'Quantity' => 1.0,
                            'UnitAmount' => 100.0,
                            'LineAmount' => 100.0,
                        ],
                    ],
                    'LineAmountTypes' => 'TaxInclusive',
                    'Type' => 'ACCREC',
                    'InvoiceNumber' => 'INV-00002',
                    'Url' => 'https://app.invoiced.com/invoices/'.$invoice->id(),
                    'DueDate' => '2022-01-19',
                ],
            ])
            ->andReturn((object) ['InvoiceID' => '1235']);

        $writer = $this->getWriter($xeroApi);

        $writer->create($invoice, self::$xeroAccount, self::$syncProfile);
    }
}
