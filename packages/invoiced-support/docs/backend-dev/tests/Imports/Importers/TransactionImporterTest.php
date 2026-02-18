<?php

namespace App\Tests\Imports\Importers;

use App\CashApplication\Models\Transaction;
use App\Imports\Importers\Spreadsheet\TransactionImporter;
use App\Imports\Models\Import;
use App\PaymentProcessing\Models\PaymentMethod;
use Mockery;

class TransactionImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->items = [['unit_cost' => 1000.1]];
        self::$invoice->save();
    }

    protected function getImporter(): TransactionImporter
    {
        return self::getService('test.importer_factory')->get('transaction');
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

        // should update invoice balance
        $this->assertEquals(500.0, self::$invoice->refresh()->balance);

        // should create a transaction
        $transaction = Transaction::where('invoice', self::$invoice->id())
            ->where('type', Transaction::TYPE_PAYMENT)
            ->oneOrNull();
        $this->assertInstanceOf(Transaction::class, $transaction);

        $expected = [
            'invoice' => self::$invoice->id(),
            'customer' => self::$invoice->customer,
            'credit_note' => null,
            'type' => Transaction::TYPE_PAYMENT,
            'currency' => 'usd',
            'method' => PaymentMethod::WIRE_TRANSFER,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'amount' => 1000.10,
            'notes' => null,
            'parent_transaction' => null,
            'metadata' => (object) ['test' => '1234'],
            'estimate' => null,
            'payment_id' => null,
        ];

        $arr = $transaction->toArray();

        foreach (['object', 'created_at', 'updated_at', 'id', 'date', 'gateway', 'gateway_id', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }

        $this->assertEquals($expected, $arr);
        $this->assertEquals('Aug-01-2014', date('M-d-Y', $transaction->date));
        $this->assertEquals(self::$company->id(), $transaction->tenant_id);

        // should create a refund
        $transaction = Transaction::where('invoice', self::$invoice->id())
            ->where('type', Transaction::TYPE_REFUND)
            ->oneOrNull();
        $this->assertInstanceOf(Transaction::class, $transaction);

        $expected = [
            'invoice' => self::$invoice->id(),
            'customer' => self::$invoice->customer,
            'credit_note' => null,
            'type' => Transaction::TYPE_REFUND,
            'currency' => 'usd',
            'method' => PaymentMethod::WIRE_TRANSFER,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'amount' => 500,
            'notes' => null,
            'parent_transaction' => null,
            'metadata' => (object) ['test' => '1234'],
            'estimate' => null,
            'payment_id' => null,
        ];

        $arr = $transaction->toArray();

        foreach (['object', 'created_at', 'updated_at', 'id', 'date', 'gateway', 'gateway_id', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }

        $this->assertEquals($expected, $arr);
        $this->assertEquals('Aug-01-2014', date('M-d-Y', $transaction->date));
        $this->assertEquals(self::$company->id(), $transaction->tenant_id);

        // should update the position
        $this->assertEquals(2, $import->position);
    }

    protected function getLines(): array
    {
        return [
            [
                self::$invoice->number,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
                'wire_transfer',
                '1234',
            ],
            [
                self::$invoice->number,
                'Aug-01-2014',
                'USD',
                '-$500',
                'wire_transfer',
                '1234',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'invoice_number',
            'date',
            'currency',
            'amount',
            'method',
            'metadata.test',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'transaction';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'invoice' => self::$invoice->id(),
                'date' => mktime(6, 0, 0, 8, 1, 2014),
                'currency' => 'USD',
                'amount' => 1000.1,
                'method' => PaymentMethod::WIRE_TRANSFER,
                'metadata' => (object) ['test' => '1234'],
            ],
            [
                '_operation' => 'create',
                'invoice' => self::$invoice->id(),
                'type' => Transaction::TYPE_REFUND,
                'date' => mktime(6, 0, 0, 8, 1, 2014),
                'currency' => 'USD',
                'amount' => 500,
                'method' => PaymentMethod::WIRE_TRANSFER,
                'metadata' => (object) ['test' => '1234'],
            ],
        ];
    }
}
