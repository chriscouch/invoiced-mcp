<?php

namespace App\Tests\Chasing\Models;

use App\Chasing\Models\InvoiceChasingCadence;
use App\Tests\AppTestCase;

class InvoiceChasingCadenceTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testValidateSchedule(): void
    {
        $cadence = new InvoiceChasingCadence();
        $cadence->name = 'Chasing Cadence';
        $cadence->chase_schedule = [
            'malformed_value',
        ];
        $this->assertFalse($cadence->save());
        $this->assertEquals("Invalid chase schedule: Malformed schedule data. Expected 'malformed_value' to be an array", (string) $cadence->getErrors());

        $cadence->chase_schedule = [
            [],
        ];
        $this->assertFalse($cadence->save());
        $this->assertEquals('Invalid chase schedule: The required options "options", "trigger" are missing.', (string) $cadence->getErrors());

        $cadence->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
            [
                'trigger' => InvoiceChasingCadence::BEFORE_DUE,
                'options' => [
                    'days' => 4,
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $this->assertTrue($cadence->save());
    }

    public function testToArray(): void
    {
        $cadence = new InvoiceChasingCadence();
        $cadence->name = 'Chasing Cadence';
        $cadence->default = false;
        $cadence->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
            [
                'trigger' => InvoiceChasingCadence::BEFORE_DUE,
                'options' => [
                    'days' => 4,
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];

        $cadence->saveOrFail();

        $expected = [
            'id' => $cadence->id(),
            'name' => 'Chasing Cadence',
            'default' => false,
            'chase_schedule' => [
                [
                    'trigger' => InvoiceChasingCadence::ON_ISSUE,
                    'options' => [
                        'hour' => 4,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::BEFORE_DUE,
                    'options' => [
                        'days' => 4,
                        'hour' => 4,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
            ],
            'created_at' => $cadence->created_at,
            'updated_at' => $cadence->updated_at,
        ];

        $this->assertEquals($expected, $cadence->toArray());
    }

    public function testMultipleDefaults(): void
    {
        $cadence1 = new InvoiceChasingCadence();
        $cadence1->name = 'First';
        $cadence1->default = true;
        $this->assertTrue($cadence1->save());

        $cadence2 = new InvoiceChasingCadence();
        $cadence2->name = 'Second';
        $cadence2->default = true;
        $this->assertFalse($cadence2->save());
        $this->assertEquals('A default cadence is already set.', (string) $cadence2->getErrors());
    }
}
