<?php

namespace App\Tests\Integrations\Intacct\Transformers;

use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctVoidFinder;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Transformers\IntacctPaymentTransformer;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Xml\Response\Result;
use SimpleXMLElement;

class IntacctPaymentTransformerTest extends AppTestCase
{
    private static string $xmlDIR;
    private static IntacctSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasIntacctAccount();

        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->integration_version = 2;
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->saveOrFail();

        self::$xmlDIR = dirname(__DIR__).'/xml/intacct_payment_reader';
    }

    /**
     * INVD-2541.
     */
    public function testCreditApplications(): void
    {
        // get payment data
        /** @var \SimpleXMLElement $paymentData */
        $paymentData = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/payment_with_credits.xml'));
        $payment = (new Result($paymentData->{'operation'}->{'result'}))->getData()[0];

        $transformer = $this->getTransformer(\Mockery::mock(IntacctVoidFinder::class));

        /** @var AccountingPayment $record */
        $record = $transformer->transform(new AccountingXmlRecord($payment));

        // TEST: build record
        $result = $record;
        $this->assertNotNull($result);
        $expected = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0000',
            values: [
                'date' => (int) mktime(6, 0, 0, 9, 1, 2021),
                'method' => 'check',
                'reference' => 'P-0000',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['name' => 'Test Customer', 'number' => 'C-191919'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1991',
                        values: ['number' => 'INV-068468'],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '1994',
                        values: ['number' => 'CN-05408504'],
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1992',
                        values: ['number' => 'INV-5408'],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '1994',
                        values: ['number' => 'CN-05408504'],
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 10000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1992',
                        values: ['number' => 'INV-5408'],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '1995',
                        values: ['number' => 'CN-354064'],
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1993',
                        values: ['number' => 'INV-0564'],
                    ),
                ),
            ]
        );
        $this->assertEquals($expected, $result);
    }

    public function testVoidCreditNoteApplication(): void
    {
        // get payment data
        /** @var \SimpleXMLElement $paymentData */
        $paymentData = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/payment_with_credits_voided.xml'));
        $payment = (new Result($paymentData->{'operation'}->{'result'}))->getData()[0];

        $voidFinder = \Mockery::mock(IntacctVoidFinder::class);
        $voidFinder->shouldReceive('findMatch')
            ->andReturn(new SimpleXMLElement('<test><RECORDNO>void_test</RECORDNO></test>'));
        $transformer = $this->getTransformer($voidFinder);

        /** @var AccountingPayment $record */
        $record = $transformer->transform(new AccountingXmlRecord($payment));

        $expected = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: 'void_test',
            voided: true
        );
        $this->assertEquals($expected, $record);
    }

    /**
     * INVD-2570.
     */
    public function testOnlyCreditNotes(): void
    {
        // get payment data
        /** @var \SimpleXMLElement $paymentData */
        $paymentData = simplexml_load_string((string) file_get_contents(self::$xmlDIR.'/payment_with_only_credits.xml'));
        $payment = (new Result($paymentData->{'operation'}->{'result'}))->getData()[0];

        $transformer = $this->getTransformer(\Mockery::mock(IntacctVoidFinder::class));

        /** @var AccountingPayment $record */
        $record = $transformer->transform(new AccountingXmlRecord($payment));

        // TEST: build record
        $result = $record;
        $this->assertNotNull($result);
        $expected = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0002',
            values: [
                'date' => (int) mktime(6, 0, 0, 9, 1, 2021),
                'method' => 'other',
                'reference' => 'P-0002',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['name' => 'Test Customer', 'number' => 'C-191919'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '2991',
                        values: ['number' => 'INV-064687'],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '2993',
                        values: ['number' => 'CN-03849'],
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 15000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '2992',
                        values: ['number' => 'INV-0854'],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '2994',
                        values: ['number' => 'CN-056468'],
                    ),
                    documentType: 'invoice',
                ),
            ],
            voided: false,
        );
        $this->assertEquals($expected, $result);
    }

    public function testTransform(): void
    {
        $transformer = $this->getTransformer(\Mockery::mock(IntacctVoidFinder::class));

        $input = new SimpleXMLElement('<arpayment>
                    <RECORDNO>0000</RECORDNO>
                    <CUSTOMERID>C-0000</CUSTOMERID>
                    <CUSTOMERNAME>Customer Zero</CUSTOMERNAME>
                    <STATE></STATE>
                    <CURRENCY>USD</CURRENCY>
                    <RECEIPTDATE>2018-06-01</RECEIPTDATE>
                    <DOCNUMBER>0000</DOCNUMBER>
                    <RECORDID></RECORDID>
                    <PAYMENTTYPE>Printed Check</PAYMENTTYPE>
                    <AUWHENCREATED></AUWHENCREATED>
                    <INVOICES>
                        <RECORD>0000</RECORD>
                        <APPLIEDAMOUNT>50</APPLIEDAMOUNT>
                        <RECORDID>INV-0564</RECORDID>
                    </INVOICES>
                    <CREDITS></CREDITS>
                </arpayment>');

        $this->assertEquals(new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0000',
            values: [
                'date' => 1527832800,
                'method' => 'check',
                'reference' => '0000',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['number' => 'C-0000', 'name' => 'Customer Zero'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '0000',
                        values: ['number' => 'INV-0564']),
                ),
            ],
        ), $transformer->transform(new AccountingXmlRecord($input)));

        $input = new SimpleXMLElement('<arpayment>
                    <RECORDNO>0001</RECORDNO>
                    <CUSTOMERID>C-0000</CUSTOMERID>
                    <CUSTOMERNAME>Customer Zero</CUSTOMERNAME>
                    <STATE></STATE>
                    <CURRENCY>USD</CURRENCY>
                    <RECEIPTDATE>2018-06-01</RECEIPTDATE>
                    <DOCNUMBER>0001</DOCNUMBER>
                    <RECORDID></RECORDID>
                    <PAYMENTTYPE>Printed Check</PAYMENTTYPE>
                    <AUWHENCREATED></AUWHENCREATED>
                    <INVOICES>
                        <RECORD>0000</RECORD>
                        <APPLIEDAMOUNT>50</APPLIEDAMOUNT>
                        <RECORDID>INV-0564</RECORDID>
                    </INVOICES>
                    <CREDITS></CREDITS>
                </arpayment>');

        $this->assertEquals(new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0001',
            values: [
                'date' => 1527832800,
                'method' => 'check',
                'reference' => '0001',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['number' => 'C-0000', 'name' => 'Customer Zero'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '0000',
                        values: ['number' => 'INV-0564']),
                ),
            ],
        ), $transformer->transform(new AccountingXmlRecord($input)));

        $input = new SimpleXMLElement('<arpayment>
                    <RECORDNO>0002</RECORDNO>
                    <CUSTOMERID>C-0000</CUSTOMERID>
                    <CUSTOMERNAME>Customer Zero</CUSTOMERNAME>
                    <STATE></STATE>
                    <CURRENCY>USD</CURRENCY>
                    <RECEIPTDATE>2018-06-01</RECEIPTDATE>
                    <DOCNUMBER>0002</DOCNUMBER>
                    <RECORDID></RECORDID>
                    <PAYMENTTYPE>Printed Check</PAYMENTTYPE>
                    <AUWHENCREATED></AUWHENCREATED>
                    <INVOICES>
                        <RECORD>0001</RECORD>
                        <APPLIEDAMOUNT>100</APPLIEDAMOUNT>
                        <RECORDID>INV-0564578</RECORDID>
                    </INVOICES>
                    <CREDITS></CREDITS>
                </arpayment>');

        $this->assertEquals(new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0002',
            values: [
                'date' => 1527832800,
                'method' => 'check',
                'reference' => '0002',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['number' => 'C-0000', 'name' => 'Customer Zero'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 10000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '0001',
                        values: ['number' => 'INV-0564578']),
                ),
            ],
        ), $transformer->transform(new AccountingXmlRecord($input)));
    }

    private function getTransformer(IntacctVoidFinder $voidFinder): IntacctPaymentTransformer
    {
        $transformer = new IntacctPaymentTransformer($voidFinder);
        $transformer->initialize(self::$intacctAccount, self::$syncProfile);

        return $transformer;
    }
}
