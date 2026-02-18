<?php

namespace App\Tests\Sending\Libs;

use App\Sending\Libs\ScheduledSendParameterValidator;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;
use InvalidArgumentException;

class ScheduledSendParameterValidatorTest extends AppTestCase
{
    public function testEmailParameters(): void
    {
        // TEST CASE: test invalid property
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, [
                'unknown_property' => 'some_value',
            ]);

            throw new \Exception('Property validation failed');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "unknown_property" does not exist. Defined options are: "bcc", "cc", "message", "role", "subject", "to".', $e->getMessage());
        }

        // TEST CASE: test typing
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, [
                'to' => 'not_array_value',
            ]);

            throw new \Exception('Typing validation failed');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "to" with value "not_array_value" is expected to be of type "array" or "null", but is of type "string".', $e->getMessage());
        }

        // TEST CASE: test 'to' contact list (malformed data)
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, [
                'to' => ['malformed_data'],
            ]);

            throw new \Exception('Contact list validation failed: malformed data');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Malformed contact in 'to' array. Array value was expected.", $e->getMessage());
        }

        // TEST CASE: test 'to' contact list (missing email address)
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, [
                'to' => [
                    [
                        'name' => 'Missing Email Contact',
                    ],
                ],
            ]);

            throw new \Exception('Contact list validation failed: missing email');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Contact is missing required property 'email' in 'to' array.", $e->getMessage());
        }

        // TEST CASE: test 'to' contact list (malformed email address)
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, [
                'to' => [
                    [
                        'name' => 'Malformed Email',
                        'email' => 'invalid_email',
                    ],
                ],
            ]);

            throw new \Exception('Contact list validation failed: malformed email');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Contact's email address is malformed", $e->getMessage());
        }

        // TEST CASE: empty parameters should be valid
        ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, []);

        // TEST CASE: valid values
        ScheduledSendParameterValidator::validate(ScheduledSend::EMAIL_CHANNEL, [
            'to' => [
                [
                    'name' => 'Contact',
                    'email' => 'email@email.com',
                ],
            ],
        ]);
    }

    public function testSmsParameters(): void
    {
        // TEST CASE: test invalid property
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::SMS_CHANNEL, [
                'bcc' => 'some_value',
            ]);

            throw new \Exception('Property validation failed');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "bcc" does not exist. Defined options are: "message", "to".', $e->getMessage());
        }

        // TEST CASE: test typing
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::SMS_CHANNEL, [
                'to' => 'not_array_value',
            ]);

            throw new \Exception('Typing validation failed');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "to" with value "not_array_value" is expected to be of type "array" or "null", but is of type "string".', $e->getMessage());
        }

        // TEST CASE: test 'to' contact list (malformed data)
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::SMS_CHANNEL, [
                'to' => ['malformed_data'],
            ]);

            throw new \Exception('Contact list validation failed: malformed data');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Malformed contact in 'to' array. Array value was expected.", $e->getMessage());
        }

        // TEST CASE: test 'to' contact list (missing phone number)
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::SMS_CHANNEL, [
                'to' => [
                    [
                        'name' => 'Missing Phone Contact',
                    ],
                ],
            ]);

            throw new \Exception('Contact list validation failed: missing phone');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Contact is missing required property 'phone' in 'to' array.", $e->getMessage());
        }

        // TEST CASE: empty parameters should be valid
        ScheduledSendParameterValidator::validate(ScheduledSend::SMS_CHANNEL, []);

        // TEST CASE: valid values
        ScheduledSendParameterValidator::validate(ScheduledSend::SMS_CHANNEL, [
            'to' => [
                [
                    'name' => 'Phone Contact',
                    'phone' => '1234567890',
                ],
            ],
        ]);
    }

    public function testLetterParameters(): void
    {
        // TEST CASE: test invalid property
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::LETTER_CHANNEL, [
                'bcc' => 'some_value',
            ]);

            throw new \Exception('Property validation failed');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "bcc" does not exist. Defined options are: "to".', $e->getMessage());
        }

        // TEST CASE: test typing
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::LETTER_CHANNEL, [
                'to' => 'not_array_value',
            ]);

            throw new \Exception('Typing validation failed');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('The option "to" with value "not_array_value" is expected to be of type "array" or "null", but is of type "string".', $e->getMessage());
        }

        // TEST CASE: test 'to' contact list (malformed data)
        try {
            ScheduledSendParameterValidator::validate(ScheduledSend::LETTER_CHANNEL, [
                'to' => ['malformed_data'],
            ]);

            throw new \Exception('Contact list validation failed: malformed data');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Invalid mailing address. Address is missing property 'address1'", $e->getMessage());
        }
    }
}
