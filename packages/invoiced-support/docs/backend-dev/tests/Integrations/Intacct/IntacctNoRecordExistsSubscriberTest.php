<?php

namespace App\Tests\Integrations\Intacct;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\EventSubscriber\IntacctNoRecordExistsSubscriber;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Tests\AppTestCase;

class IntacctNoRecordExistsSubscriberTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::$invoice->metadata = (object) [
            'intacct_document_type' => 'Sales Invoice',
        ];
        self::$invoice->saveOrFail();
    }

    public function testParseInvoiceKey(): void
    {
        $error1 = new IntegrationApiException('DL03000005 No record exits for the invoicekey 123456 [Support ID: xxxxxxxxxxxxxxxxxxxxxxxx]');
        $error2 = new IntegrationApiException('Unexpected error message');

        $this->assertEquals('123456', IntacctNoRecordExistsSubscriber::parseInvoiceKey($error1));
        $this->assertNull(IntacctNoRecordExistsSubscriber::parseInvoiceKey($error2));
    }

    public function testSuccessfulResolve(): void
    {
        // create mapping for testing
        $this->mapCustomer(self::$customer, '1');
        $invoiceMapping = $this->mapInvoice(self::$invoice, '2');

        $client = \Mockery::mock(IntacctApi::class);
        $client->shouldReceive('getOrderEntryTransactionPrRecordKey')
            ->withArgs(['Sales Invoice', self::$invoice->number])
            ->andReturn('3');

        $subscriber = new IntacctNoRecordExistsSubscriber($client);
        $subscriber->updateInvoiceMapping('2');

        $this->assertEquals(3, $invoiceMapping->refresh()->accounting_id);
    }

    public function testFailedResolve(): void
    {
        $client = \Mockery::mock(IntacctApi::class);
        $client->shouldNotReceive('getOrderEntryTransactionPrRecordKey');

        $subscriber = new IntacctNoRecordExistsSubscriber($client);

        $subscriber->updateInvoiceMapping('99');

        $client->shouldReceive('getOrderEntryTransactionPrRecordKey')
            ->withArgs(['Sales Invoice', self::$invoice->number])
            ->andThrow(new IntegrationApiException('test exception'));

        try {
            // error uses invoicekey 3 because the mapping was updated in the testSuccessfulResolve
            $subscriber->updateInvoiceMapping('3');

            throw new \Exception('should throw IntacctResolveException');
        } catch (IntegrationApiException $e) {
            $this->assertEquals('test exception', $e->getMessage());
        }
    }

    private function mapInvoice(Invoice $invoice, string $accountingId): AccountingInvoiceMapping
    {
        $mapping = new AccountingInvoiceMapping();
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->source = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->accounting_id = $accountingId;
        $mapping->invoice = $invoice;
        $mapping->save();

        return $mapping;
    }

    private function mapCustomer(Customer $customer, string $accountingId): AccountingCustomerMapping
    {
        $mapping = new AccountingCustomerMapping();
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->source = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->accounting_id = $accountingId;
        $mapping->customer = $customer;
        $mapping->save();

        return $mapping;
    }
}
