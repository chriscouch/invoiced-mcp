<?php

namespace App\Tests\AccountsReceivable\Models;

use App\Chasing\Models\InvoiceChasingCadence;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Tests\AppTestCase;
use App\Core\Orm\Exception\ModelException;

class InvoiceDeliveryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::$company->features->enable('smart_chasing');
        self::$company->features->enable('invoice_chasing');
    }

    public function testValidateSchedule(): void
    {
        $delivery = new InvoiceDelivery();
        $delivery->invoice = $this->buildInvoice();
        $delivery->chase_schedule = [
            'malformed_value',
        ];
        $this->assertFalse($delivery->save());
        $this->assertEquals("Invalid chase schedule: Malformed schedule data. Expected 'malformed_value' to be an array", (string) $delivery->getErrors());

        $delivery->chase_schedule = [
            [],
        ];
        $this->assertFalse($delivery->save());
        $this->assertEquals('Invalid chase schedule: The required options "options", "trigger" are missing.', (string) $delivery->getErrors());

        $delivery->chase_schedule = [
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
        $this->assertTrue($delivery->save());
    }

    public function testValidateScheduleTemplateId(): void
    {
        // test that cadence_id is set to null when no cadence exists
        $delivery = new InvoiceDelivery();
        $delivery->invoice = $this->buildInvoice();
        $delivery->cadence_id = 999;
        $delivery->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];

        $this->assertTrue($delivery->save());
        $this->assertNull($delivery->cadence_id);

        $template = new InvoiceChasingCadence();
        $template->name = 'Test Cadence';
        $template->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 7,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $template->saveOrFail();

        // test that cadence_id is set to null when the cadence doesn't
        // match the template's cadence
        $delivery->cadence_id = (int) $template->id();
        $this->assertTrue($delivery->save());
        $this->assertNull($delivery->cadence_id);

        // test that cadence_id is set when the cadence template exists
        // and the delivery's chase schedule matches the template's.
        $delivery->cadence_id = (int) $template->id();
        $delivery->chase_schedule = $template->chase_schedule;
        $this->assertTrue($delivery->save());
        $this->assertEquals((int) $template->id(), $delivery->cadence_id);
    }

    public function testVerifyEmails(): void
    {
        // test whitespace removal
        $delivery = new InvoiceDelivery();
        $delivery->invoice = $this->buildInvoice();
        $delivery->emails = 'a@test.com, b@test.com';
        $delivery->saveOrFail();

        $this->assertEquals('a@test.com,b@test.com', $delivery->emails); // whitespace should be removed

        // test email formatting
        $delivery->emails = 'a@test.com, b@test'; // b@test is invalid
        try {
            $delivery->saveOrFail();
            throw new \Exception('Failed to validate email formatting');
        } catch (ModelException $e) {
            $this->assertEquals("Failed to save InvoiceDelivery: Invalid email address 'b@test'", $e->getMessage());
        }

        // test duplicate removal
        $delivery->emails = 'a@test.com, a@test.com';
        $delivery->saveOrFail();
        $this->assertEquals('a@test.com', $delivery->emails);

        // test schedule refresh (should not update schedule)
        $delivery->emails = 'a@test.com,b@test.com';
        $delivery->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'hour' => 4,
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                ],
            ],
        ];
        $this->assertTrue($delivery->save());
        $delivery->processed = true; // setting processed to one to ensure the chase_schedule is not updated when emails change
        $this->assertTrue($delivery->save());
        $delivery->emails = 'a@test.com, b@test.com';
        $this->assertTrue($delivery->save());
        $this->assertTrue($delivery->processed);

        // test schedule refresh (should have updated schedule)
        // NOTE: this test case directly depends on the one above it
        $delivery->emails = 'a@test.com';
        $this->assertTrue($delivery->save());
        $this->assertFalse($delivery->processed); // was reset due to email change
    }

    public function testToArray(): void
    {
        $invoice = $this->buildInvoice();
        $delivery = new InvoiceDelivery();
        $delivery->invoice = $invoice;
        $delivery->emails = 'a@test.com,b@test.com';
        $delivery->chase_schedule = [
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

        $expected = [
            'id' => null,
            'emails' => 'a@test.com,b@test.com',
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
            'cadence_id' => null,
            'disabled' => false,
            'last_sent_email' => null,
            'last_sent_sms' => null,
            'last_sent_letter' => null,
            'created_at' => $delivery->created_at,
            'updated_at' => $delivery->updated_at,
        ];

        $this->assertEquals($expected, $delivery->toArray());
    }

    private function buildInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];

        $invoice->saveOrFail();

        return $invoice;
    }
}
