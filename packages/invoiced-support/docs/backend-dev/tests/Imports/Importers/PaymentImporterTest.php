<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Payment;
use App\Imports\Importers\Spreadsheet\PaymentImporter;
use App\Imports\Models\Import;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\Enums\IntegrationType;
use App\PaymentProcessing\Models\PaymentMethod;
use Mockery;

class PaymentImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasEstimate();
        self::hasInvoice();
        self::hasCredit();
        self::hasUnappliedCreditNote();
    }

    protected function getImporter(): PaymentImporter
    {
        return self::getService('test.importer_factory')->get('payment');
    }

    public function testRunCreate(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $records = $importer->build($mapping, $lines, [], $import);
        $result = $importer->run($records, [], $import);

        // verify result
        $this->assertEquals(2, $result->getNumCreated());
        $this->assertEquals(0, $result->getNumUpdated());

        // should create an unapplied payment
        $payment = Payment::where('customer', self::$customer->id())
            ->where('amount', 1000.10)
            ->oneOrNull();
        $this->assertInstanceOf(Payment::class, $payment);

        $expected = [
            'ach_sender_id' => null,
            'amount' => 1000.10,
            'balance' => 1000.10,
            'bank_feed_transaction_id' => null,
            'charge' => null,
            'currency' => 'usd',
            'customer' => self::$customer->id(),
            'matched' => null,
            'metadata' => new \stdClass(),
            'method' => PaymentMethod::WIRE_TRANSFER,
            'notes' => null,
            'pdf_url' => null,
            'reference' => '1234',
            'source' => 'imported',
            'voided' => false,
            'surcharge_percentage' => 0.0
        ];

        $arr = $payment->toArray();

        foreach (['object', 'created_at', 'updated_at', 'id', 'date'] as $property) {
            unset($arr[$property]);
        }

        $this->assertEquals($expected, $arr);
        $this->assertEquals('Aug-01-2014', date('M-d-Y', $payment->date));
        $this->assertEquals(self::$company->id(), $payment->tenant_id);

        // should create an accounting mapping
        $mapping = AccountingPaymentMapping::find($payment->id);
        $this->assertInstanceOf(AccountingPaymentMapping::class, $mapping);
        $this->assertEquals(IntegrationType::Intacct, $mapping->getIntegrationType());
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertEquals('accounting_system', $mapping->source);

        // should create an applied payment
        $payment = Payment::where('customer', self::$customer->id())
            ->where('amount', 4)
            ->oneOrNull();
        $this->assertInstanceOf(Payment::class, $payment);

        $expected = [
            'ach_sender_id' => '6104873529',
            'amount' => 4,
            'balance' => 0,
            'bank_feed_transaction_id' => null,
            'charge' => null,
            'currency' => 'usd',
            'customer' => self::$customer->id(),
            'matched' => null,
            'metadata' => new \stdClass(),
            'method' => PaymentMethod::CHECK,
            'notes' => null,
            'reference' => '456',
            'source' => 'imported',
            'voided' => false,
            'surcharge_percentage' => 0.0
        ];

        $arr = $payment->toArray();

        foreach (['object', 'created_at', 'updated_at', 'id', 'date', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }

        $this->assertEquals($expected, $arr);
        $this->assertEquals('Aug-01-2014', date('M-d-Y', $payment->date));
        $this->assertEquals(self::$company->id(), $payment->tenant_id);

        // should update the position
        $this->assertEquals(2, $import->position);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Void Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->amount = 100;
        $payment->reference = 'update test';
        $payment->saveOrFail();

        $mapping = ['account_number', 'reference', 'notes'];
        $lines = [
            [
                $customer->number,
                'update test',
                'test',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals('test', $payment->refresh()->notes);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Void Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->amount = 100;
        $payment->reference = 'void test';
        $payment->saveOrFail();

        $mapping = ['account_number', 'reference'];
        $lines = [
            [
                $customer->number,
                'void test',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($payment->refresh()->voided);
    }

    protected function getMapping(): array
    {
        return [
            'customer',
            'date',
            'currency',
            'amount',
            'method',
            'reference',
            'type',
            'document_type',
            'invoice',
            'estimate',
            'credit_note',
            'amount_applied',
            'ach_sender_id',
            'accounting_system',
            'accounting_id',
        ];
    }

    protected function getLines(): array
    {
        return [
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'wire_transfer',
                '1234',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'intacct',
                '1234',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$4',
                'check',
                '456',
                'invoice',
                '',
                'INV-00001',
                '',
                '',
                '$1',
                '6104873529',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'check',
                '456',
                'estimate',
                '',
                '',
                'EST-00001',
                '',
                '$1',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'check',
                '456',
                'credit_note',
                'invoice',
                'INV-00001',
                '',
                'CN-00001',
                '$1',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'check',
                '456',
                'convenience_fee',
                '',
                '',
                '',
                '',
                '$1',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'check',
                '456',
                'credit',
                '',
                '',
                '',
                '',
                '$1',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'check',
                '456',
                'applied_credit',
                'invoice',
                'INV-00001',
                '',
                '',
                '$1',
            ],
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'payment';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => ['name' => self::$customer->name],
                'date' => mktime(6, 0, 0, 8, 1, 2014),
                'currency' => 'USD',
                'amount' => 1000.1,
                'method' => PaymentMethod::WIRE_TRANSFER,
                'reference' => '1234',
                'source' => 'imported',
                'applied_to' => [],
                'ach_sender_id' => null,
                'accounting_system' => IntegrationType::Intacct,
                'accounting_id' => '1234',
            ],
            [
                '_operation' => 'create',
                'customer' => ['name' => self::$customer->name],
                'date' => mktime(6, 0, 0, 8, 1, 2014),
                'currency' => 'USD',
                'amount' => 4.0,
                'method' => PaymentMethod::CHECK,
                'reference' => '456',
                'source' => 'imported',
                'applied_to' => [
                    [
                        'type' => 'invoice',
                        'invoice' => self::$invoice->id(),
                        'amount' => 1.0,
                    ],
                    [
                        'type' => 'estimate',
                        'estimate' => self::$estimate->id(),
                        'amount' => 1.0,
                    ],
                    [
                        'type' => 'credit_note',
                        'document_type' => 'invoice',
                        'credit_note' => self::$creditNote->id(),
                        'invoice' => self::$invoice->id(),
                        'amount' => 1.0,
                    ],
                    [
                        'type' => 'convenience_fee',
                        'amount' => 1.0,
                    ],
                    [
                        'type' => 'credit',
                        'amount' => 1.0,
                    ],
                    [
                        'type' => 'applied_credit',
                        'document_type' => 'invoice',
                        'invoice' => self::$invoice->id(),
                        'amount' => 1.0,
                    ],
                ],
                'ach_sender_id' => '6104873529',
                'accounting_system' => null,
                'accounting_id' => null,
            ],
        ];
    }
}
