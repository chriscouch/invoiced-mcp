<?php

namespace App\Tests\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Sms\Models\SmsTemplate;
use App\Tests\AppTestCase;
use App\Core\Orm\Exception\ModelException;

class ChasingCadenceTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static ChasingCadence $cadence2;
    private static SmsTemplate $smsTemplate;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'test';
        $emailTemplate->type = EmailTemplate::TYPE_CHASING;
        $emailTemplate->name = 'Test';
        $emailTemplate->subject = 'Test';
        $emailTemplate->body = 'Testing...';
        $emailTemplate->saveOrFail();

        $emailTemplate2 = new EmailTemplate();
        $emailTemplate2->id = 'test2';
        $emailTemplate2->type = EmailTemplate::TYPE_CHASING;
        $emailTemplate2->name = 'Test';
        $emailTemplate2->subject = 'Test';
        $emailTemplate2->body = 'Testing...';
        $emailTemplate2->saveOrFail();

        self::$smsTemplate = new SmsTemplate();
        self::$smsTemplate->name = 'Test';
        self::$smsTemplate->message = 'Your account is past due';
        self::$smsTemplate->saveOrFail();
    }

    public function testCalculateNextRunDaily(): void
    {
        $currentTime = 1550679737;

        $cadence = new ChasingCadence();
        $cadence->tenant_id = (int) self::$company->id();
        $cadence->frequency = ChasingCadence::FREQUENCY_DAILY;
        $cadence->time_of_day = 7;

        $this->assertEquals(1550732400, ChasingCadence::nextRun($cadence, $currentTime));
    }

    public function testCalculateNextRunDailySameLastRun(): void
    {
        $currentTime = (int) mktime(7, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));

        $cadence = new ChasingCadence();
        $cadence->tenant_id = (int) self::$company->id();
        $cadence->frequency = ChasingCadence::FREQUENCY_DAILY;
        $cadence->time_of_day = 7;
        $cadence->last_run = $currentTime;

        // The next run time should be 1 day after the last run
        $this->assertEquals(date('c', $currentTime + 86400), date('c', (int) ChasingCadence::nextRun($cadence, $currentTime)));
    }

    public function testCalculateNextRunDayOfWeek(): void
    {
        $currentTime = 1550679737;

        $cadence = new ChasingCadence();
        $cadence->tenant_id = (int) self::$company->id();
        $cadence->frequency = ChasingCadence::FREQUENCY_DAY_OF_WEEK;
        $cadence->time_of_day = 7;
        $cadence->run_date = 1;

        $this->assertEquals(1551078000, ChasingCadence::nextRun($cadence, $currentTime));
    }

    public function testCalculateNextRunDayOfWeekRunDays(): void
    {
        $currentTime = 1550679737;

        $cadence = new ChasingCadence();
        $cadence->tenant_id = (int) self::$company->id();
        $cadence->frequency = ChasingCadence::FREQUENCY_DAY_OF_WEEK;
        $cadence->time_of_day = 7;
        $cadence->run_days = '1,2';

        $this->assertEquals(1551078000, ChasingCadence::nextRun($cadence, $currentTime));
    }

    public function testCalculateNextRunDayOfMonth(): void
    {
        $currentTime = 1550679737;

        $cadence = new ChasingCadence();
        $cadence->tenant_id = (int) self::$company->id();
        $cadence->frequency = ChasingCadence::FREQUENCY_DAY_OF_MONTH;
        $cadence->time_of_day = 7;
        $cadence->run_date = 1;

        $this->assertEquals(1551423600, ChasingCadence::nextRun($cadence, $currentTime));
    }

    public function testCreateNoSteps(): void
    {
        $cadence = new ChasingCadence();
        $cadence->name = 'Test';
        $cadence->time_of_day = 7;
        $this->assertFalse($cadence->save());
    }

    public function testCreateInvalidSteps(): void
    {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage("Could not save chasing cadence steps: Action is missing\nName is missing\nSchedule is missing");
        self::getService('test.transaction_manager')->perform(function () {
            $cadence = new ChasingCadence();
            $cadence->name = 'Test';
            $cadence->time_of_day = 7;
            $cadence->steps = [
                [
                    'not valid' => true,
                ],
            ];

            $cadence->saveOrFail();
        });
    }

    public function testCreateInvalidSyntax(): void
    {
        $cadence = new ChasingCadence();
        $cadence->name = 'Invalid';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL;
        $cadence->assignment_conditions = 'notAVariable';
        $this->assertFalse($cadence->save());
    }

    public function testCreateInvalidAge(): void
    {
        $this->expectException(ModelException::class);
        self::getService('test.transaction_manager')->perform(function () {
            $cadence = new ChasingCadence();
            $cadence->name = 'Invalid';
            $cadence->time_of_day = 7;
            $cadence->steps = [
                [
                    'name' => 'First Step',
                    'schedule' => 'age:-1',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                ],
            ];
            $cadence->saveOrFail();
        });
    }

    public function testCreateInvalidPastDueAge(): void
    {
        $this->expectException(ModelException::class);
        self::getService('test.transaction_manager')->perform(function () {
            $cadence = new ChasingCadence();
            $cadence->name = 'Invalid';
            $cadence->time_of_day = 7;
            $cadence->steps = [
                [
                    'name' => 'First Step',
                    'schedule' => 'past_due_age:-1',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                ],
            ];
            $cadence->saveOrFail();
        });
    }

    public function testCreate(): void
    {
        self::$cadence = new ChasingCadence();
        $this->assertTrue(self::$cadence->create([
            'name' => 'Test',
            'time_of_day' => 7,
            'steps' => [
                [
                    'name' => 'Email',
                    'schedule' => 'age:7',
                    'action' => ChasingCadenceStep::ACTION_EMAIL,
                    'email_template_id' => 'test',
                ],
                [
                    'name' => 'Letter',
                    'schedule' => 'past_due_age:0',
                    'action' => ChasingCadenceStep::ACTION_MAIL,
                ],
                [
                    'name' => 'Text Notice',
                    'action' => ChasingCadenceStep::ACTION_SMS,
                    'schedule' => 'past_due_age:5',
                    'sms_template_id' => self::$smsTemplate->id(),
                ],
                [
                    'name' => 'Final Notice',
                    'schedule' => 'past_due_age:7',
                    'action' => ChasingCadenceStep::ACTION_PHONE,
                    'assigned_user_id' => self::getService('test.user_context')->get()->id(),
                ],
            ],
        ]));

        $this->assertEquals(self::$company->id(), self::$cadence->tenant_id);

        self::$cadence2 = new ChasingCadence();
        self::$cadence2->name = 'Conditional';
        self::$cadence2->time_of_day = 7;
        self::$cadence2->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        self::$cadence2->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_CONDITIONAL;
        self::$cadence2->assignment_conditions = 'customer.country in ["US", "CA"] and customer.metadata.entity_id == "100"';
        $this->assertTrue(self::$cadence2->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $cadences = ChasingCadence::all();

        $this->assertCount(2, $cadences);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$cadence->name = 'Test Cadence';
        $this->assertTrue(self::$cadence->save());
    }

    /**
     * @depends testCreate
     */
    public function testEditSteps(): void
    {
        $steps = self::$cadence->toArray()['steps'];

        array_splice($steps, 1, 1, [
            [
                'name' => 'Another Email',
                'action' => 'email',
                'schedule' => 'past_due_age:3',
                'email_template_id' => 'test2',
            ],
        ]);
        self::$cadence->steps = $steps;
        $this->assertTrue(self::$cadence->save());
    }

    /**
     * @depends testCreate
     */
    public function testEditInvalidSteps(): void
    {
        self::$cadence->steps = [
            [
                'not valid' => true,
            ],
        ];

        $this->assertFalse(self::$cadence->save());
        $this->assertEquals("Could not save chasing cadence steps: Action is missing\nName is missing\nSchedule is missing", self::$cadence->getErrors()[0]['error']);
        $this->assertTrue(self::$cadence->persisted());

        $this->assertEquals('Test Cadence', self::$cadence->refresh()->name);
        $this->assertCount(4, self::$cadence->getSteps());
    }

    /**
     * @depends testCreate
     * @depends testEditSteps
     */
    public function testEditWithAssignedCustomers(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->chasing_cadence_id = (int) self::$cadence->id();
        $customer->saveOrFail();

        self::$cadence->name = 'Testing';
        self::$cadence->time_of_day = 8;
        $this->assertTrue(self::$cadence->save());

        self::$cadence->steps = [
            [
                'name' => 'Email',
                'schedule' => 'age:7',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Another Email',
                'action' => 'email',
                'schedule' => 'past_due_age:3',
                'email_template_id' => 'test2',
            ],
            [
                'name' => 'Text Notice',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'schedule' => 'past_due_age:5',
                'sms_template_id' => self::$smsTemplate->id(),
            ],
            [
                'name' => 'Final Notice',
                'schedule' => 'past_due_age:7',
                'action' => ChasingCadenceStep::ACTION_ESCALATE,
                'assigned_user_id' => self::getService('test.user_context')->get()->id(),
            ],
        ];
        $this->assertTrue(self::$cadence->save());
    }

    /**
     * @depends testCreate
     * @depends testEditSteps
     */
    public function testEditScheduleWithAssignedCustomers(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->chasing_cadence_id = (int) self::$cadence->id();
        $customer->saveOrFail();

        self::$cadence->steps = [
            [
                'name' => 'Email',
                'schedule' => 'age:8',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Another Email',
                'action' => 'email',
                'schedule' => 'past_due_age:3',
                'email_template_id' => 'test2',
            ],
            [
                'name' => 'Text Notice',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'schedule' => 'past_due_age:5',
                'sms_template_id' => self::$smsTemplate->id(),
            ],
            [
                'name' => 'Final Notice',
                'schedule' => 'past_due_age:7',
                'action' => ChasingCadenceStep::ACTION_ESCALATE,
                'assigned_user_id' => self::getService('test.user_context')->get()->id(),
            ],
        ];
        $this->assertFalse(self::$cadence->save());
    }

    /**
     * @depends testCreate
     * @depends testEditSteps
     */
    public function testAddStepsWithAssignedCustomers(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->chasing_cadence_id = (int) self::$cadence->id();
        $customer->saveOrFail();

        self::$cadence->steps = [
            [
                'name' => 'Email',
                'schedule' => 'age:7',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Another Email',
                'action' => 'email',
                'schedule' => 'past_due_age:3',
                'email_template_id' => 'test2',
            ],
            [
                'name' => 'Text Notice',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'schedule' => 'past_due_age:5',
                'sms_template_id' => self::$smsTemplate->id(),
            ],
            [
                'name' => 'Another Mail Notice',
                'action' => ChasingCadenceStep::ACTION_MAIL,
                'schedule' => 'past_due_age:7',
            ],
            [
                'name' => 'Final Notice',
                'schedule' => 'past_due_age:7',
                'action' => ChasingCadenceStep::ACTION_PHONE,
                'assigned_user_id' => self::getService('test.user_context')->get()->id(),
            ],
        ];
        $this->assertFalse(self::$cadence->save());
    }

    /**
     * @depends testCreate
     * @depends testEditWithAssignedCustomers
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$cadence->id,
            'object' => 'chasing_cadence',
            'name' => 'Testing',
            'time_of_day' => 8,
            'frequency' => ChasingCadence::FREQUENCY_DAILY,
            'run_date' => null,
            'min_balance' => null,
            'paused' => false,
            'assignment_mode' => 'none',
            'assignment_conditions' => '',
            'last_run' => 0,
            'next_run' => self::$cadence->next_run,
            'steps' => [
                [
                    'name' => 'Email',
                    'action' => 'email',
                    'schedule' => 'age:7',
                    'assigned_user_id' => null,
                    'email_template_id' => 'test',
                    'sms_template_id' => null,
                    'role_id' => null,
                ],
                [
                    'name' => 'Another Email',
                    'action' => 'email',
                    'schedule' => 'past_due_age:3',
                    'email_template_id' => 'test2',
                    'assigned_user_id' => null,
                    'sms_template_id' => null,
                    'role_id' => null,
                ],
                [
                    'name' => 'Text Notice',
                    'action' => 'sms',
                    'schedule' => 'past_due_age:5',
                    'assigned_user_id' => null,
                    'email_template_id' => null,
                    'sms_template_id' => self::$smsTemplate->id(),
                    'role_id' => null,
                ],
                [
                    'name' => 'Final Notice',
                    'action' => 'escalate',
                    'schedule' => 'past_due_age:7',
                    'assigned_user_id' => self::getService('test.user_context')->get()->id(),
                    'email_template_id' => null,
                    'sms_template_id' => null,
                    'role_id' => null,
                ],
            ],
            'created_at' => self::$cadence->created_at,
            'updated_at' => self::$cadence->updated_at,
            'run_days' => null,
        ];

        $result = self::$cadence->toArray();
        foreach ($result['steps'] as &$step) {
            unset($step['id']);
            unset($step['created_at']);
            unset($step['updated_at']);
        }
        $this->assertEquals($expected, $result);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertFalse(self::$cadence->delete());
        Customer::where('chasing_cadence_id', self::$cadence->id())->delete();
        $this->assertTrue(self::$cadence->delete());
    }

    public function testCreateDefaults(): void
    {
        $cadence1 = new ChasingCadence();
        $cadence1->name = 'Default';
        $cadence1->time_of_day = 7;
        $cadence1->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence1->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_DEFAULT;
        $cadence1->saveOrFail();

        $this->assertEquals(ChasingCadence::ASSIGNMENT_MODE_DEFAULT, $cadence1->assignment_mode);

        $cadence2 = new ChasingCadence();
        $cadence2->name = 'Default2';
        $cadence2->time_of_day = 7;
        $cadence2->steps = [
            [
                'name' => 'First Step',
                'schedule' => 'age:0',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence2->assignment_mode = ChasingCadence::ASSIGNMENT_MODE_DEFAULT;
        $cadence2->saveOrFail();

        $this->assertEquals(ChasingCadence::ASSIGNMENT_MODE_DEFAULT, $cadence2->assignment_mode);

        // there can only be 1 default
        $this->assertEquals(ChasingCadence::ASSIGNMENT_MODE_NONE, $cadence1->refresh()->assignment_mode);
    }
}
