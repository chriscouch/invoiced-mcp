<?php

namespace App\Tests\Integrations\Xero\Writers;

use App\CashApplication\Models\Payment;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Writers\XeroCreditNoteWriter;
use App\Integrations\Xero\Writers\XeroCustomerWriter;
use App\Integrations\Xero\Writers\XeroInvoiceWriter;
use App\Integrations\Xero\Writers\XeroPaymentWriter;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class XeroPaymentWriterTest extends AppTestCase
{
    private const PAYMENT_DEPOSIT_ACCOUNT = 'test_deposit_account_id';

    private static StatsdClient $statsd;
    private static XeroSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$payment = new Payment();
        self::$payment->setCustomer(self::$customer);
        self::$payment->date = 1640143279;
        self::$payment->amount = 100;
        self::$payment->currency = 'usd';
        self::$payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => self::$invoice,
                'amount' => 100,
            ],
        ];
        self::$payment->saveOrFail();

        self::hasXeroAccount();
        self::$syncProfile = new XeroSyncProfile();
        self::$syncProfile->payment_accounts = [['method' => '*', 'currency' => '*', 'account' => self::PAYMENT_DEPOSIT_ACCOUNT]];
        self::$syncProfile->write_payments = true;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();

        self::$statsd = new StatsdClient();
    }

    private function getWriter(XeroApi $xeroApi): XeroPaymentWriter
    {
        $customerWriter = new XeroCustomerWriter($xeroApi);
        $invoiceWriter = new XeroInvoiceWriter($xeroApi, $customerWriter, 'https://app.invoiced.com');
        $creditNoteWriter = new XeroCreditNoteWriter($xeroApi, $customerWriter, 'https://app.invoiced.com');
        $writer = new XeroPaymentWriter($xeroApi, $customerWriter, $creditNoteWriter, $invoiceWriter);
        $writer->setStatsd(self::$statsd);

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $writer = $this->getWriter($xeroApi);
        $this->assertFalse($writer->isEnabled(new XeroSyncProfile(['write_payments' => false])));
        $this->assertTrue($writer->isEnabled(new XeroSyncProfile(['write_payments' => true])));
    }

    public function testCreate(): void
    {
        $xeroApi = Mockery::mock(XeroApi::class);
        $xeroApi->shouldReceive('setAccount');
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Contacts', [
                    'where' => 'Name=="Sherlock"',
                    'includeArchived' => 'true',
                ],
            ])
            ->andReturn([(object) ['ContactID' => '1234']]);
        $xeroApi->shouldReceive('getMany')
            ->withArgs([
                'Invoices', [
                    'where' => 'Type=="ACCREC" AND InvoiceNumber=="INV-00001"',
                ],
            ])
            ->andReturn([(object) ['InvoiceID' => '1235']]);
        $xeroApi->shouldReceive('create')
            ->withArgs([
                'BatchPayments',
                [
                    'Date' => '2021-12-21',
                    'Reference' => (int) self::$payment->id(),
                    'Account' => [
                        'AccountID' => self::PAYMENT_DEPOSIT_ACCOUNT,
                    ],
                    'Payments' => [
                        0 => [
                            'Invoice' => [
                                'InvoiceID' => '1235',
                            ],
                            'Amount' => 100.0,
                        ],
                    ],
                ],
            ])
            ->andReturn((object) ['BatchPaymentID' => '1234']);

        $writer = $this->getWriter($xeroApi);
        $writer->create(self::$payment, self::$xeroAccount, self::$syncProfile);

        /** @var AccountingPaymentMapping $mapping */
        $mapping = AccountingPaymentMapping::find(self::$payment->id());
        $this->assertEquals(IntegrationType::Xero->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }
}
