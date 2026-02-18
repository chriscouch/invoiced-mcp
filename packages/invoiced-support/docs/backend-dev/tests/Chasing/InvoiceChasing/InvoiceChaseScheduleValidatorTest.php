<?php

namespace App\Tests\Chasing\InvoiceChasing;

use App\Chasing\InvoiceChasing\InvoiceChaseScheduleValidator;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class InvoiceChaseScheduleValidatorTest extends AppTestCase
{
    /**
     * The schedule array should be a 2d array. An array of steps where each step is
     * also represented as an array. This method tests the case when the schedule array
     * includes an element that isn't an array.
     */
    public function testMalformedData(): void
    {
        $invalidScheduleData = [
            'some_value',
            [],
        ];

        try {
            InvoiceChaseScheduleValidator::validate($invalidScheduleData);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate malformed data');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Malformed schedule data. Expected 'some_value' to be an array", $e->getMessage());
        }
    }

    /**
     * Tests the validation of the step format.
     */
    public function testStepResolver(): void
    {
        $invalidStep = []; // step is missing required values

        try {
            InvoiceChaseScheduleValidator::validate([$invalidStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate step required properties');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The required options "options", "trigger" are missing.', $e->getMessage());
        }

        $invalidStep = [
            'trigger' => '1', // string should be numeric
            'options' => [],
        ];

        try {
            InvoiceChaseScheduleValidator::validate([$invalidStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate step types');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "trigger" with value "1" is invalid. Accepted values are: 0, 1, 2, 3, 4, 5.', $e->getMessage());
        }

        // valid - should not throw exception
        InvoiceChaseScheduleValidator::validate([
            [
                'trigger' => 0,
                'options' => [
                    'hour' => 12,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ]);
    }

    /**
     * Tests that "on issue" step options are validated.
     */
    public function testOnIssueResolver(): void
    {
        $badStep = [
            'trigger' => InvoiceChasingCadence::ON_ISSUE,
            'options' => [
                'days' => 0, // no options are supported for "on issue"
            ],
        ];

        try {
            InvoiceChaseScheduleValidator::validate([$badStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate "on issue" options');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "days" does not exist. Defined options are: "email", "hour", "letter", "role", "sms".', $e->getMessage());
        }
    }

    /**
     * Tests that "before due" step options are validated.
     */
    public function testBeforeDueResolver(): void
    {
        $badStep = [
            'trigger' => InvoiceChasingCadence::BEFORE_DUE,
            'options' => [], // missing required option 'days'
        ];

        try {
            InvoiceChaseScheduleValidator::validate([$badStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate "before due" options');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The required options "days", "email", "hour", "letter", "sms" are missing.', $e->getMessage());
        }
    }

    /**
     * Tests that "after due" step options are validated.
     */
    public function testAfterDueResolver(): void
    {
        $badStep = [
            'trigger' => InvoiceChasingCadence::AFTER_DUE,
            'options' => [], // missing required option 'days'
        ];

        try {
            InvoiceChaseScheduleValidator::validate([$badStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate "after due" options');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The required options "days", "email", "hour", "letter", "sms" are missing.', $e->getMessage());
        }
    }

    /**
     * Tests that "after issue" step options are validated.
     * INVD-4012.
     */
    public function testAfterIssueResolver(): void
    {
        $steps = [
            [
                'trigger' => InvoiceChasingCadence::AFTER_ISSUE,
                'options' => [
                    'hour' => 12,
                    'days' => 1,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
            [
                'trigger' => InvoiceChasingCadence::AFTER_ISSUE,
                'options' => [
                    'hour' => 12,
                    'days' => 2,
                    'email' => false,
                    'sms' => true,
                    'letter' => true,
                ],
            ],
        ];

        InvoiceChaseScheduleValidator::validate($steps);
        $this->assertTrue(true);
    }

    /**
     * Tests that "repeater" step options are validated.
     */
    public function testRepeaterResolver(): void
    {
        // CASE 1: missing options
        $badStep = [
            'trigger' => InvoiceChasingCadence::REPEATER,
            'options' => [], // missing required option 'days'
        ];

        try {
            InvoiceChaseScheduleValidator::validate([$badStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate "repeater" options');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The required options "days", "email", "hour", "letter", "repeats", "sms" are missing.', $e->getMessage());
        }

        // CASE 2: invalid options
        $badStep = [
            'trigger' => InvoiceChasingCadence::REPEATER,
            'options' => [
                'email' => false,
                'sms' => false,
                'letter' => false,
                'hour' => 7,
                'days' => 0, // 0 days should not be allowed
                'repeats' => 1,
            ],
        ];

        // invalid options
        try {
            InvoiceChaseScheduleValidator::validate([$badStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate "repeater" options');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('Value "days" must be greater than or equal to 1.', $e->getMessage());
        }
    }

    /**
     * Tests that "absolute" step options are validated.
     */
    public function testAbsoluteResolver(): void
    {
        $badStep = [
            'trigger' => InvoiceChasingCadence::ABSOLUTE,
            'options' => [], // missing required option 'date'
        ];

        try {
            InvoiceChaseScheduleValidator::validate([$badStep]);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate "absolute" options');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The required options "date", "email", "hour", "letter", "sms" are missing.', $e->getMessage());
        }
    }

    /**
     * Tests that an error is thrown when two steps have the same trigger and hour.
     *
     * @dataProvider duplicateSteps
     */
    public function testDuplicates(array $steps): void
    {
        $this->expectException(InvalidArgumentException::class);
        InvoiceChaseScheduleValidator::validate($steps);
    }

    public function testStepLimit(): void
    {
        $chaseSchedule = [];
        for ($i = 0; $i < 101; ++$i) {
            // The schedule count is the first check in the validator.
            // There is no need to use valid step data in each step
            // to test the limit.
            $chaseSchedule[] = [];
        }

        try {
            InvoiceChaseScheduleValidator::validate($chaseSchedule);

            // an exception should've been thrown and the `catch` block should've been executed
            throw new \Exception('Failed to validate step limit');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('Chasing schedules cannot exceed '.InvoiceChaseScheduleValidator::STEP_LIMIT.' steps.', $e->getMessage());
        }
    }

    public function duplicateSteps(): array
    {
        $now = CarbonImmutable::now();

        return [
            'on_issue' => [[
                [
                    'trigger' => InvoiceChasingCadence::ON_ISSUE,
                    'options' => [
                        'hour' => 12,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::ON_ISSUE,
                    'options' => [
                        'hour' => 12,
                        'email' => false,
                        'sms' => true,
                        'letter' => true,
                    ],
                ],
            ]],
            'before_due' => [[
                [
                    'trigger' => InvoiceChasingCadence::BEFORE_DUE,
                    'options' => [
                        'hour' => 12,
                        'days' => 4,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::BEFORE_DUE,
                    'options' => [
                        'hour' => 12,
                        'days' => 4,
                        'email' => false,
                        'sms' => true,
                        'letter' => true,
                    ],
                ],
            ]],
            'after_due' => [[
                [
                    'trigger' => InvoiceChasingCadence::AFTER_DUE,
                    'options' => [
                        'hour' => 12,
                        'days' => 6,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::AFTER_DUE,
                    'options' => [
                        'hour' => 12,
                        'days' => 6,
                        'email' => false,
                        'sms' => true,
                        'letter' => true,
                    ],
                ],
            ]],
            'absolute' => [[
                [
                    'trigger' => InvoiceChasingCadence::ABSOLUTE,
                    'options' => [
                        'hour' => 12,
                        'date' => $now->toIso8601String(),
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::ABSOLUTE,
                    'options' => [
                        'hour' => 12,
                        'date' => $now->toIso8601String(),
                        'email' => false,
                        'sms' => true,
                        'letter' => true,
                    ],
                ],
            ]],
            'after_issue' => [[
                [
                    'trigger' => InvoiceChasingCadence::AFTER_ISSUE,
                    'options' => [
                        'hour' => 12,
                        'date' => $now->toIso8601String(),
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::AFTER_ISSUE,
                    'options' => [
                        'hour' => 12,
                        'date' => $now->toIso8601String(),
                        'email' => false,
                        'sms' => true,
                        'letter' => true,
                    ],
                ],
            ]],
            // only one repeater is allowed
            'repeater' => [[
                [
                    'trigger' => InvoiceChasingCadence::REPEATER,
                    'options' => [
                        'hour' => 11,
                        'days' => 3,
                        'repeats' => 8,
                        'email' => true,
                        'sms' => false,
                        'letter' => false,
                    ],
                ],
                [
                    'trigger' => InvoiceChasingCadence::REPEATER,
                    'options' => [
                        'hour' => 12,
                        'days' => 7,
                        'repeats' => 4,
                        'email' => false,
                        'sms' => true,
                        'letter' => true,
                    ],
                ],
            ]],
        ];
    }
}
