<?php

namespace App\Tests\ActivityLog;

use App\ActivityLog\Libs\DiffCalculator;
use App\Tests\AppTestCase;
use stdClass;

class DiffCalculatorTest extends AppTestCase
{
    public function testDiff(): void
    {
        $differ = new DiffCalculator();
        $a = [
            'test' => 10,
            'int_float_test' => 1,
        ];

        $b = [
            'test' => 11,
            'present' => true,
            'int_float_test' => 1.0,
        ];

        $expected = [
            'test' => 10,
            'present' => null,
        ];
        $this->assertEquals($expected, $differ->diff($a, $b));
    }

    public function testDiffWithEmptySecondValue(): void
    {
        $differ = new DiffCalculator();
        $a = [
            'test1' => 0,
        ];

        $b = [
            'test' => 1,
        ];

        $expected = [
            'test1' => 0,
            'test' => null,
        ];
        $this->assertEquals($expected, $differ->diff($a, $b));
    }

    public function testDiffMultiDimensional(): void
    {
        $differ = new DiffCalculator();
        $a = [
            'test' => 10,
            'items' => [
                [
                    'amount' => 100,
                ],
            ],
        ];

        $b = [
            'test' => 11,
            'items' => [
                [
                    'amount' => 101,
                ],
                [
                    'test' => true,
                ],
            ],
        ];

        $expected = [
            'test' => 10,
            'items' => [
                [
                    'amount' => 100,
                ],
            ],
        ];
        $this->assertEquals($expected, $differ->diff($a, $b));
    }

    public function testDiffWithObjects(): void
    {
        $differ = new DiffCalculator();
        $a = [
            'metadata' => new stdClass(),
        ];

        $b = [
            'metadata' => new stdClass(),
        ];

        $expected = [];
        $this->assertEquals($expected, $differ->diff($a, $b));

        $a['metadata']->test = 10;
        $b['metadata']->test = 11;
        $a['metadata']->blah = true;

        $expected = [
            'metadata' => (object) [
                'test' => 10,
                'blah' => true,
            ],
        ];
        $this->assertEquals($expected, $differ->diff($a, $b));
    }

    public function testMetadataPopulated(): void
    {
        $differ = new DiffCalculator();
        $a = [
            'metadata' => new stdClass(),
        ];

        $b = [
            'metadata' => (object) [
                'test' => 'test',
            ],
        ];

        $expected = [
            'metadata' => (object) [
                'test' => null,
            ],
        ];
        $this->assertEquals($expected, $differ->diff($a, $b));

        $a = [
            'metadata' => (object) [
                'test' => 'test',
            ],
        ];

        $b = [
            'metadata' => (object) [
                'test' => 'test',
                'test2' => 'test2',
            ],
        ];

        $expected = [
            'metadata' => (object) [
                'test2' => null,
            ],
        ];

        $this->assertEquals($expected, $differ->diff($a, $b));

        $a = [
            'metadata' => (object) [
                'test' => 'test',
                'test2' => 'test2',
            ],
        ];

        $b = [
            'metadata' => (object) [
                'test' => 'test',
            ],
        ];

        $expected = [
            'metadata' => (object) [
                'test2' => 'test2',
            ],
        ];

        $this->assertEquals($expected, $differ->diff($a, $b));

        $a = [
            'metadata' => (object) [
                'test' => 'test',
                'test2' => 'test2',
            ],
        ];

        $b = [];

        $this->assertEquals($a, $differ->diff($a, $b));
    }

    public function testUpdatedAt(): void
    {
        $differ = new DiffCalculator();
        $a = [
            'updated_at' => 'test',
            'test' => 'test2',
            'items' => [
                [
                    'updated_at' => 'test',
                    'test' => 'test2',
                ],
            ],
        ];

        $b = [
            'updated_at' => 'test2',
            'test' => 'test3',
            'items' => [
                [
                    'updated_at' => 'test2',
                    'test' => 'test3',
                ],
            ],
        ];

        $this->assertEquals($a, $differ->diff($a, $b));

        $a = [
            'updated_at' => 'test',
            'test' => 'test2',
            'items' => [
                [
                    'updated_at' => 'test',
                    'test' => 'test2',
                ],
            ],
        ];

        $b = [
            'updated_at' => '1',
            'test' => 'test2',
            'items' => [
                [
                    'updated_at' => '1',
                    'test' => 'test2',
                ],
            ],
        ];
        $this->assertEquals([], $differ->diff($a, $b));

        $a = [
            'updated_at' => 'test',
            'test' => 'test2',
            'items' => [
                [
                    'updated_at' => 'test',
                    'test' => 'test2',
                ],
            ],
        ];

        $b = [
            'updated_at' => 'test',
            'test' => 'test2',
            'items' => [
                [
                    'updated_at' => '1',
                    'test' => 'test3',
                ],
            ],
        ];
        $this->assertEquals(['items' => [
            [
                'updated_at' => 'test',
                'test' => 'test2',
            ],
        ]], $differ->diff($a, $b));
    }

    public function testInnerArrayTypes(): void
    {
        $a = [
            'items' => [
                [
                    'updated_at' => 'test',
                    'test' => 'test2',
                ],
            ],
        ];

        $b = [
            'items' => [
                [
                    'updated_at' => '1',
                    'test' => 'test2',
                ],
                [
                    'updated_at' => '1',
                    'test' => 'test2',
                ],
            ],
        ];
        $differ = new DiffCalculator();
        $this->assertEquals([
            'items' => [
                [
                    'updated_at' => 'test',
                    'test' => 'test2',
                ],
            ],
        ], $differ->diff($a, $b));
        $this->assertEquals([
            'items' => [
                [
                    'updated_at' => '1',
                    'test' => 'test2',
                ],
                [
                    'updated_at' => '1',
                    'test' => 'test2',
                ],
            ],
        ], $differ->diff($b, $a));
    }

    public function testDiffNullValues(): void
    {
        $a = [
            'payment_source' => [
                'routing_number' => null,
                'last4' => '1234',
            ],
        ];

        $b = [
            'payment_source' => [
                'last4' => '1234',
            ],
        ];
        $differ = new DiffCalculator();
        $this->assertEquals([], $differ->diff($a, $b));
        $this->assertEquals([], $differ->diff($b, $a));
    }
}
