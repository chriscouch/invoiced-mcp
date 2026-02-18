<?php

namespace App\Tests\Integrations\Textract;

use App\AccountsPayable\Models\Vendor;
use App\Core\Statsd\StatsdClient;
use App\Integrations\Textract\Enums\TextractProcessingStage;
use App\Integrations\Textract\Libs\ExpenseAnalyzer;
use App\Integrations\Textract\Models\TextractImport;
use App\Integrations\Textract\ValueObjects\AnalyzedParameters;
use App\Tests\AppTestCase;
use Aws\Result;
use Aws\Sdk;
use Aws\Textract\TextractClient;
use Mockery;

class ExpenseAnalyzerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testAnalyze(): void
    {
        $statsd = Mockery::mock(StatsdClient::class);
        $awsResult = Mockery::mock(Result::class);
        $textractClient = Mockery::mock(TextractClient::class);
        $textractClient->shouldReceive('getExpenseAnalysis')->andReturn($awsResult);
        $aws = Mockery::mock(Sdk::class);
        $aws->shouldReceive('createTextract')->andReturn($textractClient);

        $analyzer = new ExpenseAnalyzer(
            self::getService('test.database'),
            self::getService('test.tenant'),
            'test',
            'test',
            'test',
            'test',
            $aws
        );
        $analyzer->setStatsd($statsd);

        $import = new class($this) extends TextractImport {
            public string $job_id = 'test';
            public ?Vendor $vendor = null;
            public array $expected = [
                'vendor' => null,
                'currency' => 'usd',
                'line_items' => [],
                'number' => null,
                'date' => null,
                'total' => 0.0,
                'address1' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
            ];

            public function __construct(private readonly ExpenseAnalyzerTest $that)
            {
                parent::__construct();
            }

            public function saveOrFail(): void
            {
                $this->that->assertEquals(TextractProcessingStage::Succeed, $this->status);
                $this->that->assertNull($this->vendor);
                $this->that->assertEquals($this->expected, array_intersect_key((array) $this->data, $this->expected));
            }
        };

        // invalid data
        $data = $this->buildAwsData([[
            'SummaryFields' => [],
            'LineItemGroups' => [],
        ]]);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $analyzer->analyze($import);

        // valid data, no currency
        $input = [
            [
                'SummaryFields' => [
                    'VENDOR_NAME' => [
                        'Text' => 'Random',
                    ],
                    'NAME' => [
                        'Text' => 'some name',
                    ],
                    'INVOICE_RECEIPT_ID' => [
                        'Text' => 'NUM',
                    ],
                    'INVOICE_RECEIPT_DATE' => [
                        'Text' => 'Sep 21, 2020',
                    ],
                    'TOTAL' => [
                        'Text' => '$100.05',
                    ],
                    'STREET' => [
                        'Text' => 'What is vendor street address',
                    ],
                    'CITY' => [
                        'Text' => 'Austin',
                    ],
                    'STATE' => [
                        'Text' => 'TX',
                    ],
                    'ZIP_CODE' => [
                        'Text' => '78738',
                    ],
                    'COUNTRY' => [
                        'Text' => 'US',
                    ],
                ],
                'LineItemGroups' => [
                    [
                        [
                            'ITEM' => 'test',
                            'EXPENSE_ROW' => 'full description',
                            'PRICE' => '10',
                        ],
                        [
                            'ITEM' => 'test',
                            'EXPENSE_ROW' => 'full description',
                            'PRICE' => '20',
                        ],
                    ],
                ],
            ],
        ];
        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();

        $import->expected = array_replace($import->expected, [
            'vendor' => 'Random',
            'number' => 'NUM',
            'date' => 'Sep 21, 2020',
            'total' => 100.05,
            'line_items' => [
                [
                    'description' => 'test',
                    'amount' => 10.0,
                ], [
                    'description' => 'test',
                    'amount' => 20.0,
                ], [
                    'description' => AnalyzedParameters::UNCATEGORIZED,
                    'amount' => 70.05,
                ],
            ],
        ]);

        $analyzer->analyze($import);

        // another currency, additional line items groups and actualized vendor data,
        // and no vendor name
        $input = [
            [
                'SummaryFields' => [
                    'NAME' => [
                        'Text' => 'some name',
                    ],
                ],
                'LineItemGroups' => [],
            ],
            [
                'SummaryFields' => [
                    'NAME' => [
                        'Text' => 'some name',
                        'LabelDetection' => 'Vendor',
                    ],
                    'INVOICE_RECEIPT_ID' => [
                        'Text' => 'NUM',
                    ],
                    'INVOICE_RECEIPT_DATE' => [
                        'Text' => 'Sep 21, 2020',
                    ],
                    'TOTAL' => [
                        'Text' => '$100.05',
                        'Currency' => 'gbp',
                    ],
                    'STREET' => [
                        'Text' => 'What is vendor street address',
                        'LabelDetection' => 'Vendor',
                    ],
                    'CITY' => [
                        'Text' => 'Austin',
                        'LabelDetection' => 'Supplier',
                    ],
                    'STATE' => [
                        'Text' => 'TX',
                        'LabelDetection' => 'Remit To',
                    ],
                    'ZIP_CODE' => [
                        'Text' => '78738',
                    ],
                    'COUNTRY' => [
                        'Text' => 'US',
                        'LabelDetection' => 'Vendor',
                    ],
                ],
                'LineItemGroups' => [],
            ],
            [
                'SummaryFields' => [],
                'LineItemGroups' => [
                    [
                        [
                            'ITEM' => 'test',
                            'EXPENSE_ROW' => 'full description',
                            'PRICE' => '10',
                        ],
                        [
                            'EXPENSE_ROW' => 'full description',
                            'PRICE' => '20',
                        ],
                    ],
                ],
            ],
        ];
        $import->vendor = null;
        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $import->expected = array_replace($import->expected, [
            'vendor' => 'some name',
            'currency' => 'gbp',
            'address1' => 'What is vendor street address',
            'city' => 'Austin',
            'state' => 'TX',
            'line_items' => [
                [
                    'description' => 'test',
                    'amount' => 10.0,
                ], [
                    'description' => 'full description',
                    'amount' => 20.0,
                ], [
                    'description' => AnalyzedParameters::UNCATEGORIZED,
                    'amount' => 70.05,
                ],
            ],
        ]);

        $analyzer->analyze($import);
    }

    private function buildAwsData(array $data): array
    {
        $result = [];
        foreach ($data as $input) {
            $inputResult = [
                'SummaryFields' => [],
                'LineItemGroups' => [],
            ];
            foreach ($input['SummaryFields'] as $key => $item) {
                $inputResult['SummaryFields'][] = [
                    'Type' => [
                        'Text' => $key,
                    ],
                    'ValueDetection' => [
                        'Text' => $item['Text'],
                    ],
                    'LabelDetection' => [
                        'Text' => $item['LabelDetection'] ?? null,
                    ],
                    'Currency' => [
                        'Code' => $item['Currency'] ?? null,
                    ],
                ];
            }

            foreach ($input['LineItemGroups'] as $group) {
                $items = [];
                foreach ($group as $lineItems) {
                    $resultItem = [];
                    foreach ($lineItems as $key => $value) {
                        $resultItem[] = [
                            'Type' => [
                                'Text' => $key,
                            ],
                            'ValueDetection' => [
                                'Text' => $value,
                            ],
                        ];
                    }

                    $items[] = [
                        'LineItemExpenseFields' => $resultItem,
                    ];
                }
                $resultGroup[] = [
                    'LineItems' => $items,
                ];
                $inputResult['LineItemGroups'] = $resultGroup;
            }

            $result[] = $inputResult;
        }

        return $result;
    }
}
