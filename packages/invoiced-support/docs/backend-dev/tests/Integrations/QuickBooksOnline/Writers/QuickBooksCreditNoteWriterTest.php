<?php

namespace App\Tests\Integrations\QuickBooksOnline\Writers;

use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCreditNoteWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCustomerWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class QuickBooksCreditNoteWriterTest extends AppTestCase
{
    private static QuickBooksOnlineSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCreditNote();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->undeposited_funds_account = 'test_account';
        self::$syncProfile->tax_code = 'TAX';
        self::$syncProfile->write_customers = true;
        self::$syncProfile->write_credit_notes = true;
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
    public function getWriter(QuickBooksApi $api): QuickBooksCreditNoteWriter
    {
        $writer = new QuickBooksCreditNoteWriter($api, new QuickBooksCustomerWriter($api));
        $writer->setStatsd(new StatsdClient());

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $writer = $this->getWriter(Mockery::mock(QuickBooksApi::class));
        $this->assertFalse($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_credit_notes' => false])));
        $this->assertTrue($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_credit_notes' => true])));
    }

    /**
     * Tests creating new customer and credit memo on QBO.
     */
    public function testCreate(): void
    {
        $expectedId = 1234;
        // expect all successful qbo responses.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getCustomerByName' => null,
            'createCustomer' => $this->getExpectedQBOObject($expectedId),
            'createCreditMemo' => $this->getExpectedQBOObject($expectedId),
            'createItem' => $this->getExpectedQBOObject($expectedId),
            'getCreditMemoByNumber' => null,
            'getAccountByName' => $this->getExpectedQBOObject($expectedId),
            'getItemByName' => $this->getExpectedQBOObject($expectedId),
            'getTaxCode' => $this->getExpectedQBOObject($expectedId),
            'setAccount' => null,
        ]);

        $writer = $this->getWriter($quickbooksApi);
        $writer->create(self::$creditNote, self::$quickbooksAccount, self::$syncProfile);

        /** @var AccountingCustomerMapping $customerMapping */
        $customerMapping = AccountingCustomerMapping::find(self::$creditNote->customer);
        /** @var AccountingCreditNoteMapping $creditNoteMapping */
        $creditNoteMapping = AccountingCreditNoteMapping::find(self::$creditNote->id());

        $this->assertNotNull($customerMapping);
        $this->assertEquals(1234, $customerMapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_INVOICED, $customerMapping->source);

        $this->assertNotNull($creditNoteMapping);
        $this->assertEquals(1234, $customerMapping->accounting_id);
        $this->assertEquals(AccountingCreditNoteMapping::SOURCE_INVOICED, $customerMapping->source);

        // delete mappings for next test
        $customerMapping->delete();
        $creditNoteMapping->delete();

        // test failed customer create
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getCustomerByName' => null,
            'getCreditMemoByNumber' => null,
            'setAccount' => null,
        ]);
        $quickbooksApi->shouldReceive('createCustomer')
            ->andThrows(new IntegrationApiException('Unknown error'));
        $writer = $this->getWriter($quickbooksApi);
        $writer->create(self::$creditNote, self::$quickbooksAccount, self::$syncProfile);

        // find reconciliation error
        /** @var ReconciliationError $error */
        $error = ReconciliationError::where('object_id', self::$creditNote->id())->oneOrNull();
        $this->assertNotNull($error);
        $this->assertEquals('Unknown error', $error->message);
    }
}
