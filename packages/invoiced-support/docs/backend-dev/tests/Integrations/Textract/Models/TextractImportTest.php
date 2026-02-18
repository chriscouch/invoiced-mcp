<?php

namespace App\Tests\Integrations\Textract\Models;

use App\Integrations\Textract\Models\TextractImport;
use App\Integrations\Textract\ValueObjects\AnalyzedParameters;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class TextractImportTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasFile();
    }

    public function testSave(): void
    {
        $import = new TextractImport();
        $import->file = self::$file;
        $import->data = (object) [
            'number' => 'NUM',
            'date' => CarbonImmutable::parse('Sep 21, 2020'),
            'total' => 100.05,
            'address1' => 'What is vendor street address',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78738',
            'line_items' => [
                [
                    'description' => AnalyzedParameters::UNCATEGORIZED,
                    'amount' => 100.05,
                ],
            ],
        ];
        $this->assertTrue($import->save());
    }
}
