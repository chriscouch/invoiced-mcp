<?php

namespace App\Tests\Imports\Importers;

use App\CashApplication\Models\BankFeedTransaction;
use App\Imports\Importers\BankFeedTransactionBaiImporter;
use App\Imports\Models\Import;
use Mockery;

class BankFeedTransactionBaiImporterTest extends ImporterTestBase
{
    protected function getImporter(): BankFeedTransactionBaiImporter
    {
        return self::getService('test.importer_factory')->get('bank_feed_transaction_bai');
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
        $this->assertEquals(17, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a bank feed transaction
        $transaction = BankFeedTransaction::where('description', 'RETURNED CHEQUE     /')
            ->oneOrNull();
        $this->assertInstanceOf(BankFeedTransaction::class, $transaction);

        $expected = [
            'amount' => 25.0,
            'cash_application_bank_account_id' => null,
            'check_number' => null,
            'created_at' => $transaction->created_at,
            'date' => $transaction->date,
            'description' => 'RETURNED CHEQUE     /',
            'id' => $transaction->id,
            'merchant_name' => null,
            'payment_by_order_of' => null,
            'payment_channel' => null,
            'payment_method' => null,
            'payment_payee' => null,
            'payment_payer' => null,
            'payment_ppd_id' => null,
            'payment_processor' => null,
            'payment_reason' => null,
            'payment_reference_number' => null,
            'transaction_id' => $transaction->transaction_id,
            'updated_at' => $transaction->updated_at,
        ];

        $arr = $transaction->toArray();
        $this->assertEquals($expected, $arr);
        $this->assertEquals('Mar-17-2006', $transaction->date->format('M-d-Y'));
        $this->assertEquals(self::$company->id(), $transaction->tenant_id);

        // should update the position
        $this->assertEquals(17, $import->position);
    }

    protected function getMapping(): array
    {
        return [];
    }

    protected function getLines(): array
    {
        return [
            'bai_text' => '01,0004,12345,060321,0829,001,80,1,2/
02,12345,0004,1,060317,,CAD,/
03,10200123456,CAD,040,+000000000000,,,045,+000000000000,,/
88,100,000000000208500,00003,V,060316,,400,000000000208500,00008,V,060316,/
16,409,000000000002500,V,060316,,,,RETURNED CHEQUE     /
16,409,000000000090000,V,060316,,,,RTN-UNKNOWN         /
16,409,000000000000500,V,060316,,,,RTD CHQ SERVICE CHRG/
16,108,000000000203500,V,060316,,,,TFR 1020 0345678    /
16,108,000000000002500,V,060316,,,,MACLEOD MALL        /
16,108,000000000002500,V,060316,,,,MASCOUCHE QUE       /
16,409,000000000020000,V,060316,,,,1000 ISLANDS MALL   /
16,409,000000000090000,V,060316,,,,PENHORA MALL        /
16,409,000000000002000,V,060316,,,,CAPILANO MALL       /
16,409,000000000002500,V,060316,,,,GALERIES LA CAPITALE/
16,409,000000000001000,V,060316,,,,PLAZA ROCK FOREST   /
49,+00000000000834000,14/
03,10200123456,CAD,040,+000000000000,,,045,+000000000000,,/
88,100,000000000111500,00002,V,060317,,400,000000000111500,00004,V,060317,/
16,108,000000000011500,V,060317,,,,TFR 1020 0345678    /
16,108,000000000100000,V,060317,,,,MONTREAL            /
16,409,000000000100000,V,060317,,,,GRANDFALL NB        /
16,409,000000000009000,V,060317,,,,HAMILTON ON         /
16,409,000000000002000,V,060317,,,,WOODSTOCK NB        /
16,409,000000000000500,V,060317,,,,GALERIES RICHELIEU  /
49,+00000000000446000,9/
98,+00000000001280000,2,25/
99,+00000000001280000,1,27/',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'bank_feed_transaction_bai';

        return $import;
    }

    protected function transformExpectedResult(array $result): array
    {
        $result = parent::transformExpectedResult($result);
        foreach ($result as &$row) {
            unset($row['transaction_id']);
        }

        return $result;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                'date' => '2006-03-17',
                'amount' => 25,
                'payment_reference_number' => null,
                'description' => 'RETURNED CHEQUE     /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 900,
                'payment_reference_number' => null,
                'description' => 'RTN-UNKNOWN         /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 5,
                'payment_reference_number' => null,
                'description' => 'RTD CHQ SERVICE CHRG/',
            ],
            [
                'date' => '2006-03-17',
                'amount' => -2035,
                'payment_reference_number' => null,
                'description' => 'TFR 1020 0345678    /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => -25,
                'payment_reference_number' => null,
                'description' => 'MACLEOD MALL        /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => -25,
                'payment_reference_number' => null,
                'description' => 'MASCOUCHE QUE       /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 200,
                'payment_reference_number' => null,
                'description' => '1000 ISLANDS MALL   /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 900,
                'payment_reference_number' => null,
                'description' => 'PENHORA MALL        /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 20,
                'payment_reference_number' => null,
                'description' => 'CAPILANO MALL       /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 25,
                'payment_reference_number' => null,
                'description' => 'GALERIES LA CAPITALE/',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 10,
                'payment_reference_number' => null,
                'description' => 'PLAZA ROCK FOREST   /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => -115,
                'payment_reference_number' => null,
                'description' => 'TFR 1020 0345678    /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => -1000,
                'payment_reference_number' => null,
                'description' => 'MONTREAL            /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 1000,
                'payment_reference_number' => null,
                'description' => 'GRANDFALL NB        /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 90,
                'payment_reference_number' => null,
                'description' => 'HAMILTON ON         /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 20,
                'payment_reference_number' => null,
                'description' => 'WOODSTOCK NB        /',
            ],
            [
                'date' => '2006-03-17',
                'amount' => 5,
                'description' => 'GALERIES RICHELIEU  /',
                'payment_reference_number' => null,
            ],
        ];
    }
}
