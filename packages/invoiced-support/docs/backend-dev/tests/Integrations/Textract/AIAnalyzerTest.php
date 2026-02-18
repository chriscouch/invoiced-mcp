<?php

namespace App\Tests\Integrations\Textract;

use App\AccountsPayable\Models\Vendor;
use App\Core\Statsd\StatsdClient;
use App\Integrations\Textract\Enums\TextractProcessingStage;
use App\Integrations\Textract\Libs\AIAnalyzer;
use App\Integrations\Textract\Models\TextractImport;
use App\Integrations\Textract\ValueObjects\AnalyzedParameters;
use App\Tests\AppTestCase;
use Aws\Result;
use Aws\Sdk;
use Aws\Textract\TextractClient;
use Mockery;

class AIAnalyzerTest extends AppTestCase
{
    private static Vendor $vendor2;

    // duplicate from AI analyzer
    private const QUERIES = [
        'vendor' => 'What is vendor name',
        'number' => 'What is invoice number',
        'date' => 'What is invoice date',
        'total' => 'What is invoice total',
        'currency' => 'What is invoice currency',
        'address1' => 'What is vendor street address',
        'city' => 'What is vendor city',
        'state' => 'What is vendor state',
        'postal_code' => 'What is vendor zip code',
        'country' => 'What is vendor country',
        'item1_name' => 'What is 1st line item name',
        'item1_amount' => 'What is 1st line item amount',
        'item2_name' => 'What is 2nd line item name',
        'item2_amount' => 'What is 2nd line item amount',
        'item3_name' => 'What is 3rd line item name',
        'item3_amount' => 'What is 3rd line item amount',
        'item4_name' => 'What is 4th line item name',
        'item4_amount' => 'What is 4th line item amount',
        'item5_name' => 'What is 5th line item name',
        'item5_amount' => 'What is 5th line item amount',
        'item6_name' => 'What is 6th line item name',
        'item6_amount' => 'What is 6th line item amount',
        'item7_name' => 'What is 7th line item name',
        'item7_amount' => 'What is 7th line item amount',
        'item8_name' => 'What is 8th line item name',
        'item8_amount' => 'What is 8th line item amount',
        'item9_name' => 'What is 9th line item name',
        'item9_amount' => 'What is 9th line item amount',
        'item10_name' => 'What is 10th line item name',
        'item10_amount' => 'What is 10th line item amount',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasVendor();
        self::$vendor2 = self::$vendor;
        self::hasVendor();

        self::$vendor->name = 'Test some vendor';
        self::$vendor->saveOrFail();
    }

    public function testAnalyze(): void
    {
        $statsd = Mockery::mock(StatsdClient::class);
        $awsResult = Mockery::mock(Result::class);
        $textractClient = Mockery::mock(TextractClient::class);
        $textractClient->shouldReceive('getDocumentAnalysis')->andReturn($awsResult);
        $aws = Mockery::mock(Sdk::class);
        $aws->shouldReceive('createTextract')->andReturn($textractClient);

        $analyzer = new AIAnalyzer(
            self::getService('test.database'),
            self::getService('test.tenant'),
            'test',
            'test',
            'test',
            'test',
            $aws,
            'test',
            '1',
        );
        $analyzer->setStatsd($statsd);

        $import = new class($this) extends TextractImport {
            public string $job_id = 'test';
            public array $expected = [
                'vendor' => 'Test some vendor',
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

            public function __construct(private readonly AIAnalyzerTest $that)
            {
                parent::__construct();
            }

            public function saveOrFail(): void
            {
                $this->that->assertEquals(TextractProcessingStage::Succeed, $this->status);
                $this->that->assertEquals($this->expected, array_intersect_key((array) $this->data, $this->expected));
            }
        };
        $import->vendor = self::$vendor;

        // invalid data
        $data = $this->buildAwsData([]);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $analyzer->analyze($import);

        // valid data, no currency
        $input = [
            'vendor' => 'Random',
            'number' => 'NUM',
            'date' => 'Sep 21, 2020',
            'total' => '$100.05',
            'address1' => 'What is vendor street address',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78738',
            'country' => 'USA',
        ];
        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();

        $import->expected = array_replace($import->expected, [
            'number' => 'NUM',
            'date' => 'Sep 21, 2020',
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
        ]);
        $analyzer->analyze($import);

        // another currency
        $input['currency'] = 'gbp';
        $input['country'] = 'US';
        $input['item1_name'] = 'Test';
        $input['item1_amount'] = '$25.57';
        $import->expected['line_items'] = [[
            'description' => 'Test',
            'amount' => 25.57,
        ], [
            'description' => AnalyzedParameters::UNCATEGORIZED,
            'amount' => 74.48,
        ]];

        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();

        $import->expected = array_replace($import->expected, [
            'currency' => 'gbp',
            'country' => 'US',
        ]);
        $analyzer->analyze($import);

        // match vendor - no name
        unset($input['vendor']);
        $import->vendor = null;
        $import->expected = array_replace($import->expected, [
            'vendor' => null,
        ]);
        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $analyzer->analyze($import);

        // more than 1 match
        $input['vendor'] = 'Test';
        $import->expected = array_replace($import->expected, [
            'vendor' => 'Test',
        ]);
        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $analyzer->analyze($import);

        // 1 match
        $input['vendor'] = self::$vendor2->name;
        $import->expected = array_replace($import->expected, [
            'vendor' => self::$vendor2->name,
        ]);
        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $analyzer->analyze($import);

        // test initial parameters
        $input['item1_name'] = 'Input2';
        $input['item1_amount'] = '$13.13';
        self::hasFile();
        $import2 = new TextractImport();
        $import2->data = (object) [
            'postal_code' => '78756',
            'line_items' => [
                [
                    'description' => 'Output1',
                    'amount' => 25.57,
                ],
                [
                    'description' => 'Output2',
                    'amount' => 25.57,
                ],
                [
                    'description' => 'Output3',
                    'amount' => 25.05,
                ],
                [
                    'description' => 'Output4',
                    'amount' => 25.05,
                ],
                [
                    'description' => AnalyzedParameters::UNCATEGORIZED,
                    'amount' => 100.05,
                ],
            ],
        ];
        $import->expected['line_items'] = [
            [
                'description' => 'Input2',
                'amount' => 13.13,
            ],
            [
                'description' => 'Output4',
                'amount' => 25.05,
            ],
            [
                'description' => 'Output3',
                'amount' => 25.05,
            ],
            [
                'description' => 'Output2',
                'amount' => 25.57,
            ],
            [
                'description' => 'Output1',
                'amount' => 25.57,
            ],
            [
                'description' => AnalyzedParameters::UNCATEGORIZED,
                'amount' => -14.32,
            ],
        ];
        $import2->job_id = uniqid();
        $import2->file = self::$file;
        $import2->saveOrFail();
        $import->parent_job_id = $import2->job_id;

        $data = $this->buildAwsData($input);
        $awsResult->shouldReceive('get')->andReturn($data)->once();
        $analyzer->analyze($import);
    }

    private function buildAwsData(array $input): array
    {
        $result = [];
        foreach ($input as $key => $item) {
            $result[] = [
                'BlockType' => 'QUERY',
                'Relationships' => [
                    [
                        'Type' => 'ANSWER',
                        'Ids' => [
                            $key,
                        ],
                    ],
                ],
                'Query' => [
                    'Text' => self::QUERIES[$key],
                ],
            ];
            $result[] = [
                'BlockType' => 'QUERY_RESULT',
                'Text' => $item,
                'Id' => $key,
            ];
        }

        return $result;
    }
}
