<?php

namespace App\Tests\Integrations\QuickBooksOnline\Writers;

use App\Core\Statsd\StatsdClient;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\Tax;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Tests\AppTestCase;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksInvoiceWriter;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\SalesTax\Models\TaxRate;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCustomerWriter;
use Carbon\CarbonImmutable;
use Mockery;

class QuickBooksInvoiceWriterTest extends AppTestCase
{
    private static QuickBooksOnlineSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->undeposited_funds_account = 'test_account';
        self::$syncProfile->tax_code = 'TAX';
        self::$syncProfile->write_customers = true;
        self::$syncProfile->write_invoices = true;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();
    }

    /**
     * Returns a \stdClass instance representing a QBO Customer object.
     * The object returned only consists of the Id property
     * because no other property is used by the writer.
     */
    public function getExpectedQBOObject(int $id): \stdClass
    {
        return (object) [
            'Id' => $id,
        ];
    }

    /**
     * Returns instance of QuickBooksCustomerWriter configured
     * for test cases.
     */
    public function getWriter(QuickBooksApi $api): QuickBooksInvoiceWriter
    {
        $writer = new QuickBooksInvoiceWriter($api, new QuickBooksCustomerWriter($api));
        $writer->setStatsd(new StatsdClient());

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $writer = $this->getWriter(Mockery::mock(QuickBooksApi::class));
        $this->assertFalse($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_invoices' => false])));
        $this->assertTrue($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_invoices' => true])));
    }

    /**
     * Tests that records issues prior to sync profiles start date
     * are not reconciled.
     */
    public function testShouldReconcile(): void
    {
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $writer = $this->getWriter($quickbooksApi);

        // check created before start date
        self::$syncProfile->invoice_start_date = strtotime('+1 day', self::$invoice->date);
        $this->assertFalse($writer->shouldReconcile(self::$invoice, self::$syncProfile));

        // check created after or equal to start date
        self::$syncProfile->invoice_start_date = self::$invoice->date;
        $this->assertTrue($writer->shouldReconcile(self::$invoice, self::$syncProfile));
    }

    /**
     * Tests creating new customer and invoice on QBO.
     */
    public function testCreate(): void
    {
        $expectedId = 1234;
        // expect all successful qbo responses.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getCustomerByName' => null,
            'createCustomer' => $this->getExpectedQBOObject($expectedId),
            'createInvoice' => $this->getExpectedQBOObject($expectedId),
            'createItem' => $this->getExpectedQBOObject($expectedId),
            'getInvoiceByNumber' => null,
            'getAccountByName' => $this->getExpectedQBOObject($expectedId),
            'getItemByName' => $this->getExpectedQBOObject($expectedId),
            'getTaxCode' => $this->getExpectedQBOObject($expectedId),
            'setAccount' => null,
        ]);

        $writer = $this->getWriter($quickbooksApi);
        $writer->create(self::$invoice, self::$quickbooksAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $customerMapping */
        $customerMapping = AccountingCustomerMapping::find(self::$invoice->customer);
        /** @var AccountingInvoiceMapping $invoiceMapping */
        $invoiceMapping = AccountingInvoiceMapping::find(self::$invoice->id());

        $this->assertNotNull($customerMapping);
        $this->assertEquals(1234, $customerMapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_INVOICED, $customerMapping->source);

        $this->assertNotNull($invoiceMapping);
        $this->assertEquals(1234, $customerMapping->accounting_id);
        $this->assertEquals(AccountingInvoiceMapping::SOURCE_INVOICED, $customerMapping->source);

        // delete mappings for next test
        $customerMapping->delete();
        $invoiceMapping->delete();

        // test failed customer create
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getCustomerByName' => null,
            'getInvoiceByNumber' => null,
            'setAccount' => null,
        ]);
        $quickbooksApi->shouldReceive('createCustomer')
            ->andThrows(new IntegrationApiException('Unknown error'));
        $writer = $this->getWriter($quickbooksApi);
        $writer->create(self::$invoice, self::$quickbooksAccount, self::$syncProfile);

        // find reconciliation error
        $error = ReconciliationError::where('object_id', self::$invoice->id())->oneOrNull();
        $this->assertNotNull($error);
        $this->assertEquals('Unknown error', $error->message);
    }

    /**
     * Stores a test TaxRate object in the database.
     */
    public function buildTestTaxRate(string $name, bool $isPercent, bool $isInclusive): TaxRate
    {
        $taxRate = new TaxRate();
        $taxRate->id = $name;
        $taxRate->name = $name;
        $taxRate->is_percent = $isPercent;
        $taxRate->value = 5;
        $taxRate->inclusive = $isInclusive;
        $taxRate->saveOrFail();

        return $taxRate;
    }

    /**
     * Tests QuickBooksInvoiceWriter::areTaxesInclusive
     * Function should return true if given array of taxes
     * are all inclusive, otherwise should return false.
     */
    public function testAreTaxesInclusive(): void
    {
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $writer = $this->getWriter($quickbooksApi);

        $inclusiveTaxRate1 = $this->buildTestTaxRate('i-t-1', true, true);
        $inclusiveTaxRate2 = $this->buildTestTaxRate('i-t-2', true, true);
        $exclusiveTaxRate1 = $this->buildTestTaxRate('e-t-1', false, false);

        $this->assertFalse($writer->areTaxesInclusive([]));
        $this->assertTrue($writer->areTaxesInclusive([
            new Tax(['rate_id' => $inclusiveTaxRate1->id()]),
        ]));
        $this->assertTrue($writer->areTaxesInclusive([
            new Tax(['rate_id' => $inclusiveTaxRate1->id()]),
            new Tax(['rate_id' => $inclusiveTaxRate2->id()]),
        ]));
        $this->assertFalse($writer->areTaxesInclusive([
            new Tax(['rate_id' => $inclusiveTaxRate1->id()]),
            new Tax(['rate_id' => $inclusiveTaxRate2->id()]),
            new Tax(['rate_id' => $exclusiveTaxRate1->id()]),
        ]));
        $this->assertFalse($writer->areTaxesInclusive([
            new Tax(['rate_id' => $exclusiveTaxRate1->id()]),
        ]));
    }

    /**
     * Tests cases of QuickBooksInvoiceWriter::isTaxInclusive.
     */
    public function testIsTaxInclusive(): void
    {
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $writer = $this->getWriter($quickbooksApi);

        // set up test tax rates.
        $taxRate1 = $this->buildTestTaxRate('tax-rate-1', true, true);
        $taxRate2 = $this->buildTestTaxRate('tax-rate-2', true, true);
        $taxRate3 = $this->buildTestTaxRate('tax-rate-3', false, false);

        // set up line items
        $taxExclusiveLineItem = new LineItem();
        $taxInclusiveItem = new LineItem(['taxes' => [new Tax(['rate_id' => $taxRate1->id()])]]);

        /**
         * Test US: should throw error when line items are tax inclusive.
         */
        // test one tax inclusive line item.
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->items = [
            $taxInclusiveItem,
        ];
        try {
            $writer->isTaxInclusive($invoice);
        } catch (SyncException $e) {
            $this->assertEquals('Syncing invoices with tax inclusive line items is not supported.', $e->getMessage());
        }

        // test tax exclusive line item, tax inclusive invoice.
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->taxes = [new Tax(['rate_id' => $taxRate1->id()])];
        $invoice->items = [
            $taxExclusiveLineItem,
        ];
        try {
            $writer->isTaxInclusive($invoice);
        } catch (SyncException $e) {
            $this->assertEquals('Tax inclusive line items does not match tax inclusive on subtotal.', $e->getMessage());
        }

        /**
         * Test non-US.
         */
        $country = self::$company->country;
        self::$company->country = 'CA';
        self::$company->saveOrFail();

        // test mixed tax inclusive line items, should throw an error.
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->items = [
            $taxExclusiveLineItem,
            $taxInclusiveItem,
        ];
        try {
            $writer->isTaxInclusive($invoice);
        } catch (SyncException $e) {
            $this->assertEquals('Line items have mismatched tax inclusive values.', $e->getMessage());
        }

        // test tax inclusive invoice, but not tax inclusive line items, should throw an error
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->taxes = [new Tax(['rate_id' => $taxRate1->id()])];
        $invoice->items = [
            $taxExclusiveLineItem,
        ];
        try {
            $writer->isTaxInclusive($invoice);
        } catch (SyncException $e) {
            $this->assertEquals('Tax inclusive line items does not match tax inclusive on subtotal.', $e->getMessage());
        }

        // test tax inclusive line items, but not tax inclusive invoice, should return true
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->items = [
            $taxInclusiveItem,
        ];
        $this->assertTrue($writer->isTaxInclusive($invoice));

        // test tax inclusive line items and tax inclusive invoice, should return true
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->taxes = [new Tax(['rate_id' => $taxRate2->id()])];
        $invoice->items = [
            $taxInclusiveItem,
        ];
        $this->assertTrue($writer->isTaxInclusive($invoice));

        // test tax exclusive line items and invoice, should return false
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->taxes = [new Tax(['rate_id' => $taxRate3->id()])];
        $invoice->items = [
            $taxExclusiveLineItem,
        ];
        $this->assertFalse($writer->isTaxInclusive($invoice));

        // test tax exclusive line items and mixed inclusive/exclusive invoice taxes, should return false
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->taxes = [new Tax(['rate_id' => $taxRate1->id()]), new Tax(['rate_id' => $taxRate3->id()])];
        $invoice->items = [
            $taxExclusiveLineItem,
        ];
        $this->assertFalse($writer->isTaxInclusive($invoice));

        // test tax inclusive line items and mixed inclusive/exclusive invoice taxes, should throw an error
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        $invoice->taxes = [new Tax(['rate_id' => $taxRate1->id()]), new Tax(['rate_id' => $taxRate3->id()])];
        $invoice->items = [
            $taxInclusiveItem,
        ];
        try {
            $writer->isTaxInclusive($invoice);
        } catch (SyncException $e) {
            $this->assertEquals('Tax inclusive line items does not match tax inclusive on subtotal.', $e->getMessage());
        }

        // reset country.
        self::$company->country = $country;
        self::$company->save();
    }

    /**
     * Tests normalization of line item amount based on invoice taxes.
     * QuickBooksInvoiceWriter::normalizeQBOLineItemAmount.
     */
    public function testNormalizeQBOLineItemAmount(): void
    {
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $writer = $this->getWriter($quickbooksApi);

        // test fixed no-rate taxes, should throw an error
        $taxes = [['rate_id' => null]];
        try {
            $writer->normalizeQBOLineItemAmount('usd', 100, $taxes);
        } catch (SyncException $e) {
            $this->assertEquals('Tax rate cannot be null.', $e->getMessage());
        }

        // test taxes that aren't percentages, should throw an error
        $taxRate = $this->buildTestTaxRate('test-tax-rate', false, false);
        $taxes = [['rate_id' => $taxRate->id()]];
        try {
            $writer->normalizeQBOLineItemAmount('usd', 100, $taxes);
        } catch (SyncException $e) {
            $this->assertEquals('Tax rate must be a percentage.', $e->getMessage());
        }

        // test calculation
        $taxRate = $this->buildTestTaxRate('t-t-1', true, true);
        $taxRate1 = $this->buildTestTaxRate('t-t-2', true, true);

        $taxes = [['rate_id' => $taxRate->id()]]; // one tax of 5 percent
        $amount = $writer->normalizeQBOLineItemAmount('usd', 100, $taxes);
        $this->assertEquals(95.23, $amount);

        $taxes = [['rate_id' => $taxRate->id()], ['rate_id' => $taxRate1->id()]]; // two taxes of 5 percent
        $amount = $writer->normalizeQBOLineItemAmount('usd', 100, $taxes);
        $this->assertEquals(90.46, $amount);
    }

    /**
     * Tests QuickBooksInvoiceWriter::buildQBOCustomFields.
     */
    public function testBuildQBOCustomFields(): void
    {
        // setup test custome fields
        self::$syncProfile->custom_field_1 = 'invoice_field1:-:1:-:Event Rep';
        self::$syncProfile->custom_field_2 = 'invoice_field2:-:2:-:Event Rep';
        self::$syncProfile->custom_field_3 = 'invoice_field3:-:3:-:Event Rep';

        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $writer = $this->getWriter($quickbooksApi);

        // test with no custom fields applied in invoice metadata
        $invoice = new Invoice();
        $expected = [];
        $this->assertEquals($expected, $writer->buildQBOCustomFields($invoice, self::$syncProfile));

        // test with first invoice field set
        $invoice = new Invoice();
        $invoice->metadata->invoice_field1 = 'test_value';
        $expected = [[
            'DefinitionId' => 1,
            'Type' => 'StringType',
            'Name' => 'Event Rep',
            'StringValue' => 'test_value',
        ]];
        $this->assertEquals($expected, $writer->buildQBOCustomFields($invoice, self::$syncProfile));
    }

    /**
     * Tests QuickBooksInvoiceWriter::buildQBOTaxDetails.
     */
    public function testBuildQBOTaxDetails(): void
    {
        $country = self::$company->country;
        self::$company->country = 'CA';
        self::$company->saveOrFail();

        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getTaxCode' => (object) [
                'SalesTaxRateList' => null,
            ],
        ]);

        self::$syncProfile->tax_code = 'TAX';
        $writer = $this->getWriter($quickbooksApi);

        // test SalesTaxRateList condition.
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();
        try {
            $writer->buildQBOTaxDetails($invoice, self::$syncProfile);
        } catch (SyncException $e) {
            $this->assertEquals($e->getMessage(), 'Default tax code must have a sales tax rate. Either set a new default taxc ode or add a sales tax rate in QuickBooks.');
        }

        // reset country.
        self::$company->country = $country;
        self::$company->save();
    }
}
