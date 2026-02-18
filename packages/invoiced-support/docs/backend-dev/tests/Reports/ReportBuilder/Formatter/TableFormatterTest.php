<?php

namespace App\Tests\Reports\ReportBuilder\Formatter;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Formatter\TableFormatter;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\Filter;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\GroupField;
use App\Reports\ReportBuilder\ValueObjects\Joins;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Reports\ReportBuilder\ValueObjects\TableReportSection;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Section;
use App\Tests\AppTestCase;

class TableFormatterTest extends AppTestCase
{
    private static string $dataDir;
    private static array $customerData;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::$dataDir = dirname(__DIR__).'/Formatter/data';
        self::$customerData = json_decode((string) file_get_contents(self::$dataDir.'/customer_data.json'), true);
    }

    public function testFormatZeroGroups(): void
    {
        SelectColumn::resetCounter();

        // Build DataQuery
        $table = new Table('customer');
        $fields = new Fields([
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'id'), name: 'Customer ID', type: ColumnType::Float),
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'), name: 'Customer Name'),
        ]);
        $filter = new Filter([
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', self::$company->id()),
        ]);

        // Build section data
        $dataQuery = new DataQuery($table, new Joins([]), $fields, $filter, new Group([]), new Sort([]));
        $section = new TableReportSection('Section 1', $dataQuery, self::$company);
        $sectionData = self::$customerData;

        // Test group-by formatting
        $tableFormatter = $this->getTableFormatter();
        $actual = $tableFormatter->format($section, $sectionData[0], []);
        $expected = $this->buildExpectedZeroGroups();

        $this->assertEquals($expected, $actual);
    }

    public function testFormatSingleGroup(): void
    {
        SelectColumn::resetCounter();

        // Build DataQuery
        $table = new Table('customer');
        $fields = new Fields([
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'id'), name: 'Customer ID', type: ColumnType::Float),
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'), name: 'Customer Name'),
        ]);
        $filter = new Filter([
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', self::$company->id()),
        ]);
        $groups = new Group([
            new GroupField(new FieldReferenceExpression(new Table('customer'), 'state', ColumnType::String, 'State'), true, true, 'State'),
        ]);

        // Build section data
        $dataQuery = new DataQuery($table, new Joins([]), $fields, $filter, $groups, new Sort([]));
        $section = new TableReportSection('Section 1', $dataQuery, self::$company);
        $sectionData = self::$customerData;

        // Test group-by formatting
        $tableFormatter = $this->getTableFormatter();
        $actual = $tableFormatter->format($section, $sectionData[0], []);
        $expected = $this->buildExpectedSingleGroupFormatting();

        $this->assertEquals($expected, $actual);
    }

    public function testFormatMultipleGroups(): void
    {
        SelectColumn::resetCounter();

        // Build DataQuery
        $table = new Table('customer');
        $fields = new Fields([
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'id'), name: 'Customer ID', type: ColumnType::Float),
            new SelectColumn(new FieldReferenceExpression(new Table('customer'), 'name'), name: 'Customer Name'),
        ]);
        $filter = new Filter([
            new FilterCondition(new FieldReferenceExpression(new Table('customer'), 'tenant_id'), '=', self::$company->id()),
        ]);
        $groups = new Group([
            new GroupField(new FieldReferenceExpression(new Table('customer'), 'state', ColumnType::String, 'State'), true, true, 'State'),
            new GroupField(new FieldReferenceExpression(new Table('customer'), 'city', ColumnType::String, 'City'), true, true, 'City'),
        ]);

        // Build section data
        $dataQuery = new DataQuery($table, new Joins([]), $fields, $filter, $groups, new Sort([]));
        $section = new TableReportSection('Section 1', $dataQuery, self::$company);
        $sectionData = self::$customerData;

        // Test group-by formatting
        $tableFormatter = $this->getTableFormatter();
        $actual = $tableFormatter->format($section, $sectionData[0], []);
        $expected = $this->buildExpectedMultipleGroupFormatting();

        $this->assertEquals($expected, $actual);
    }

    //
    // Helpers
    //

    private function getTableFormatter(): TableFormatter
    {
        return new TableFormatter();
    }

    /**
     * Builds the expected result for testFormatZeroGroups().
     */
    private function buildExpectedZeroGroups(): Section
    {
        $table = $this->buildNestedTableGroup(
            [
                ['name' => 'Customer ID', 'type' => 'float'],
                ['name' => 'Customer Name', 'type' => 'string'],
            ],
            null,
            [
                [
                    // id
                    ['customer' => '1', 'formatted' => '1', 'value' => '1'],
                    // name
                    ['customer' => '1', 'formatted' => 'Test Customer 1', 'value' => 'Test Customer 1'],
                ],
                [
                    // id
                    ['customer' => '2', 'formatted' => '2', 'value' => '2'],
                    // name
                    ['customer' => '2', 'formatted' => 'Test Customer 2', 'value' => 'Test Customer 2'],
                ],
                [
                    // id
                    ['customer' => '3', 'formatted' => '3', 'value' => '3'],
                    // name
                    ['customer' => '3', 'formatted' => 'Test Customer 3', 'value' => 'Test Customer 3'],
                ],
                [
                    // id
                    ['customer' => '4', 'formatted' => '4', 'value' => '4'],
                    // name
                    ['customer' => '4', 'formatted' => 'Test Customer 4', 'value' => 'Test Customer 4'],
                ],
                [
                    // id
                    ['customer' => '5', 'formatted' => '5', 'value' => '5'],
                    // name
                    ['customer' => '5', 'formatted' => 'Test Customer 5', 'value' => 'Test Customer 5'],
                ],
                [
                    // id
                    ['customer' => '6', 'formatted' => '6', 'value' => '6'],
                    // name
                    ['customer' => '6', 'formatted' => 'Test Customer 6', 'value' => 'Test Customer 6'],
                ],
                [
                    // id
                    ['customer' => '7', 'formatted' => '7', 'value' => '7'],
                    // name
                    ['customer' => '7', 'formatted' => 'Test Customer 7', 'value' => 'Test Customer 7'],
                ],
                [
                    // id
                    ['customer' => '8', 'formatted' => '8', 'value' => '8'],
                    // name
                    ['customer' => '8', 'formatted' => 'Test Customer 8', 'value' => 'Test Customer 8'],
                ],
                [
                    // id
                    ['customer' => '9', 'formatted' => '9', 'value' => '9'],
                    // name
                    ['customer' => '9', 'formatted' => 'Test Customer 9', 'value' => 'Test Customer 9'],
                ],
            ]
        );

        $expectedSection = new Section('Section 1');
        $expectedSection->addGroup($table);

        return $expectedSection;
    }

    /**
     * Builds the expected result for testFormatSingleGroup().
     */
    private function buildExpectedSingleGroupFormatting(): Section
    {
        $tableColumns = [
            ['name' => 'Customer ID', 'type' => 'float'],
            ['name' => 'Customer Name', 'type' => 'string'],
        ];

        $texasTable = $this->buildNestedTableGroup(
            $tableColumns,
            [
                'name' => 'State',
                'type' => 'string',
                'value' => [
                    'customer' => '1',
                    'formatted' => 'TX',
                    'value' => 'TX',
                ],
            ],
            [
                [
                    // id
                    ['customer' => '1', 'formatted' => '1', 'value' => '1'],
                    // name
                    ['customer' => '1', 'formatted' => 'Test Customer 1', 'value' => 'Test Customer 1'],
                ],
                [
                    // id
                    ['customer' => '2', 'formatted' => '2', 'value' => '2'],
                    // name
                    ['customer' => '2', 'formatted' => 'Test Customer 2', 'value' => 'Test Customer 2'],
                ],
                [
                    // id
                    ['customer' => '3', 'formatted' => '3', 'value' => '3'],
                    // name
                    ['customer' => '3', 'formatted' => 'Test Customer 3', 'value' => 'Test Customer 3'],
                ],
                [
                    // id
                    ['customer' => '4', 'formatted' => '4', 'value' => '4'],
                    // name
                    ['customer' => '4', 'formatted' => 'Test Customer 4', 'value' => 'Test Customer 4'],
                ],
                [
                    // id
                    ['customer' => '5', 'formatted' => '5', 'value' => '5'],
                    // name
                    ['customer' => '5', 'formatted' => 'Test Customer 5', 'value' => 'Test Customer 5'],
                ],
            ]
        );

        $njTable = $this->buildNestedTableGroup(
            $tableColumns,
            [
                'name' => 'State',
                'type' => 'string',
                'value' => [
                    'customer' => '6',
                    'formatted' => 'NJ',
                    'value' => 'NJ',
                ],
            ],
            [
                [
                    // id
                    ['customer' => '6', 'formatted' => '6', 'value' => '6'],
                    // name
                    ['customer' => '6', 'formatted' => 'Test Customer 6', 'value' => 'Test Customer 6'],
                ],
                [
                    // id
                    ['customer' => '7', 'formatted' => '7', 'value' => '7'],
                    // name
                    ['customer' => '7', 'formatted' => 'Test Customer 7', 'value' => 'Test Customer 7'],
                ],
            ]
        );

        $floridaTable = $this->buildNestedTableGroup(
            $tableColumns,
            [
                'name' => 'State',
                'type' => 'string',
                'value' => [
                    'customer' => '8',
                    'formatted' => 'FL',
                    'value' => 'FL',
                ],
            ],
            [
                [
                    // id
                    ['customer' => '8', 'formatted' => '8', 'value' => '8'],
                    // name
                    ['customer' => '8', 'formatted' => 'Test Customer 8', 'value' => 'Test Customer 8'],
                ],
                [
                    // id
                    ['customer' => '9', 'formatted' => '9', 'value' => '9'],
                    // name
                    ['customer' => '9', 'formatted' => 'Test Customer 9', 'value' => 'Test Customer 9'],
                ],
            ]
        );

        $table = $this->buildNestedTableGroup($tableColumns, null, [
            $texasTable,
            $njTable,
            $floridaTable,
        ]);

        $expectedSection = new Section('Section 1');
        $expectedSection->addGroup($table);

        return $expectedSection;
    }

    /**
     * Builds the expected result for testFormatMultipleGroups().
     */
    private function buildExpectedMultipleGroupFormatting(): Section
    {
        $tableColumns = [
            ['name' => 'Customer ID', 'type' => 'float'],
            ['name' => 'Customer Name', 'type' => 'string'],
        ];

        $texasTable = $this->buildNestedTableGroup(
            $tableColumns,
            [
                'name' => 'State',
                'type' => 'string',
                'value' => [
                    'customer' => '1',
                    'formatted' => 'TX',
                    'value' => 'TX',
                ],
            ],
            [
                $this->buildNestedTableGroup(
                    $tableColumns,
                    [
                        'name' => 'City',
                        'type' => 'string',
                        'value' => [
                            'customer' => '1',
                            'formatted' => 'Austin',
                            'value' => 'Austin',
                        ],
                    ],
                    [
                        [
                            // id
                            ['customer' => '1', 'formatted' => '1', 'value' => '1'],
                            // name
                            ['customer' => '1', 'formatted' => 'Test Customer 1', 'value' => 'Test Customer 1'],
                        ],
                        [
                            // id
                            ['customer' => '2', 'formatted' => '2', 'value' => '2'],
                            // name
                            ['customer' => '2', 'formatted' => 'Test Customer 2', 'value' => 'Test Customer 2'],
                        ],
                        [
                            // id
                            ['customer' => '3', 'formatted' => '3', 'value' => '3'],
                            // name
                            ['customer' => '3', 'formatted' => 'Test Customer 3', 'value' => 'Test Customer 3'],
                        ],
                    ]
                ),
                $this->buildNestedTableGroup(
                    $tableColumns,
                    [
                        'name' => 'City',
                        'type' => 'string',
                        'value' => [
                            'customer' => '4',
                            'formatted' => 'Dallas',
                            'value' => 'Dallas',
                        ],
                    ],
                    [
                        [
                            // id
                            ['customer' => '4', 'formatted' => '4', 'value' => '4'],
                            // name
                            ['customer' => '4', 'formatted' => 'Test Customer 4', 'value' => 'Test Customer 4'],
                        ],
                        [
                            // id
                            ['customer' => '5', 'formatted' => '5', 'value' => '5'],
                            // name
                            ['customer' => '5', 'formatted' => 'Test Customer 5', 'value' => 'Test Customer 5'],
                        ],
                    ]
                ),
            ]
        );

        $njTable = $this->buildNestedTableGroup(
            $tableColumns,
            [
                'name' => 'State',
                'type' => 'string',
                'value' => [
                    'customer' => '6',
                    'formatted' => 'NJ',
                    'value' => 'NJ',
                ],
            ],
            [
                $this->buildNestedTableGroup(
                    $tableColumns,
                    [
                        'name' => 'City',
                        'type' => 'string',
                        'value' => [
                            'customer' => '6',
                            'formatted' => 'Newark',
                            'value' => 'Newark',
                        ],
                    ],
                    [
                        [
                            // id
                            ['customer' => '6', 'formatted' => '6', 'value' => '6'],
                            // name
                            ['customer' => '6', 'formatted' => 'Test Customer 6', 'value' => 'Test Customer 6'],
                        ],
                        [
                            // id
                            ['customer' => '7', 'formatted' => '7', 'value' => '7'],
                            // name
                            ['customer' => '7', 'formatted' => 'Test Customer 7', 'value' => 'Test Customer 7'],
                        ],
                    ]
                ),
            ]
        );

        $floridaTable = $this->buildNestedTableGroup(
            $tableColumns,
            [
                'name' => 'State',
                'type' => 'string',
                'value' => [
                    'customer' => '8',
                    'formatted' => 'FL',
                    'value' => 'FL',
                ],
            ],
            [
                $this->buildNestedTableGroup(
                    $tableColumns,
                    [
                        'name' => 'City',
                        'type' => 'string',
                        'value' => [
                            'customer' => '8',
                            'formatted' => 'Miami',
                            'value' => 'Miami',
                        ],
                    ],
                    [
                        [
                            // id
                            ['customer' => '8', 'formatted' => '8', 'value' => '8'],
                            // name
                            ['customer' => '8', 'formatted' => 'Test Customer 8', 'value' => 'Test Customer 8'],
                        ],
                    ]
                ),
                $this->buildNestedTableGroup(
                    $tableColumns,
                    [
                        'name' => 'City',
                        'type' => 'string',
                        'value' => [
                            'customer' => '9',
                            'formatted' => 'Orlando',
                            'value' => 'Orlando',
                        ],
                    ],
                    [
                        [
                            // id
                            ['customer' => '9', 'formatted' => '9', 'value' => '9'],
                            // name
                            ['customer' => '9', 'formatted' => 'Test Customer 9', 'value' => 'Test Customer 9'],
                        ],
                    ]
                ),
            ]
        );

        $table = $this->buildNestedTableGroup($tableColumns, null, [
            $texasTable,
            $njTable,
            $floridaTable,
        ]);

        $expectedSection = new Section('Section 1');
        $expectedSection->addGroup($table);

        return $expectedSection;
    }

    /**
     * Helper used to build NestedTableGroup in one line.
     */
    private function buildNestedTableGroup(array $columns, ?array $groupHeader, array $rows): NestedTableGroup
    {
        $group = new NestedTableGroup($columns);
        if ($groupHeader) {
            $group->setGroupHeader($groupHeader);
        }

        $group->addRows($rows);

        return $group;
    }
}
