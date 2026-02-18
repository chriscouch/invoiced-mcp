<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\CustomerChasing\CustomerChasingPlanner;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingBalance;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Sms\Models\SmsTemplate;
use App\Tests\AppTestCase;

class ChasingPlannerTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static SmsTemplate $smsTemplate;
    private static Customer $customer2;
    private static Customer $customer3;
    private static Customer $customer4;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$company->features->enable('multi_currency');
        self::hasCustomer();

        self::$smsTemplate = new SmsTemplate();
        self::$smsTemplate->name = 'Test';
        self::$smsTemplate->message = 'Your account is past due';
        self::$smsTemplate->saveOrFail();

        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'test';
        $emailTemplate->type = EmailTemplate::TYPE_CHASING;
        $emailTemplate->name = 'Test';
        $emailTemplate->subject = 'Test';
        $emailTemplate->body = 'Testing...';
        $emailTemplate->saveOrFail();

        self::$cadence = new ChasingCadence();
        self::$cadence->name = 'Test';
        self::$cadence->time_of_day = 7;
        self::$cadence->steps = [
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
            [
                'name' => 'Second Text Notice',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'schedule' => 'past_due_age:6',
                'sms_template_id' => self::$smsTemplate->id(),
            ],
            [
                'name' => 'Second Text Notice',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'schedule' => 'past_due_age:6',
                'sms_template_id' => self::$smsTemplate->id(),
            ],
            [
                'name' => 'escalation 1',
                'action' => ChasingCadenceStep::ACTION_ESCALATE,
                'schedule' => 'past_due_age:9',
            ],
            [
                'name' => 'escalation 2',
                'action' => ChasingCadenceStep::ACTION_ESCALATE,
                'schedule' => 'past_due_age:8',
            ],
            [
                'name' => 'Third Text Notice',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'schedule' => 'past_due_age:26',
                'sms_template_id' => self::$smsTemplate->id(),
            ],
        ];
        self::$cadence->saveOrFail();

        self::$customer->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer->next_chase_step = (int) self::$cadence->getSteps()[0]->id();
        self::$customer->saveOrFail();

        self::$customer2 = new Customer();
        self::$customer2->name = 'Multi-currency';
        self::$customer2->country = 'US';
        self::$customer2->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer2->next_chase_step = (int) self::$cadence->getSteps()[0]->id();
        self::$customer2->saveOrFail();

        self::$customer3 = new Customer();
        self::$customer3->name = 'Finished Run';
        self::$customer3->country = 'US';
        self::$customer3->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer3->next_chase_step = null;
        self::$customer3->saveOrFail();

        self::$customer4 = new Customer();
        self::$customer4->name = 'Chasing Paused';
        self::$customer4->country = 'US';
        self::$customer4->chase = false;
        self::$customer4->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer4->next_chase_step = (int) self::$cadence->getSteps()[0]->id();
        self::$customer4->saveOrFail();
    }

    private function getPlanner(): CustomerChasingPlanner
    {
        return self::getService('test.chasing_planner');
    }

    public function testGetCustomers(): void
    {
        $runner = $this->getPlanner();
        $customers = $runner->getCustomers(self::$cadence);
        $this->assertCount(2, $customers);
        $this->assertEquals(self::$customer->id(), $customers[0]->id());
        $this->assertEquals(self::$customer2->id(), $customers[1]->id());
    }

    public function testStepShouldRunNoBalance(): void
    {
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'age:7';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 0),
            new Money('usd', 0),
            0,
            null
        );

        $this->assertFalse($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testStepShouldRunMinBalance(): void
    {
        self::$cadence->min_balance = 50;
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'age:7';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 5000),
            new Money('usd', 0),
            14,
            null
        );

        $this->assertTrue($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 100),
            new Money('usd', 0),
            0,
            null
        );

        $this->assertFalse($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testStepShouldRunAge(): void
    {
        self::$cadence->min_balance = null;
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'age:7';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 100),
            new Money('usd', 0),
            7,
            null
        );

        $this->assertTrue($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testStepShouldRunAgeTooEarly(): void
    {
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'age:7';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 100),
            new Money('usd', 0),
            6,
            null
        );

        $this->assertFalse($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testStepShouldRunPastDueAge(): void
    {
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'past_due_age:0';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 100),
            new Money('usd', 100),
            0,
            0
        );

        $this->assertTrue($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testStepShouldRunPastDueAgeTooSoon(): void
    {
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'past_due_age:7';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 100),
            new Money('usd', 0),
            0,
            6
        );

        $this->assertFalse($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testStepShouldRunPastDueAgeNotPastDue(): void
    {
        $runner = $this->getPlanner();
        $step = new ChasingCadenceStep();
        $step->schedule = 'past_due_age:0';

        $chasingBalance = new ChasingBalance(
            self::$customer,
            [],
            new Money('usd', 100),
            new Money('usd', 0),
            0,
            null
        );

        $this->assertFalse($runner->stepShouldRun(self::$cadence, $chasingBalance, $step));
    }

    public function testPlanInvoiceBalance(): void
    {
        $cadence = new ChasingCadence();
        $cadence->name = 'Test';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'Step A',
                'schedule' => 'age:7',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Step B',
                'schedule' => 'age:35',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Step C',
                'schedule' => 'age:70',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
        ];
        $cadence->saveOrFail();

        $steps = $cadence->getSteps();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->chasing_cadence_id = (int) $cadence->id();
        $customer->next_chase_step = (int) $steps[0]->id();
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = strtotime('-21 days');
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = strtotime('-7 days');
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $planner = $this->getPlanner();
        $plan = $planner->plan($cadence);
        $expected = [
            [
                'customer' => $customer->id(),
                'step' => 'Step A',
                'nextStep' => 'Step B',
            ],
        ];
        $this->assertEquals($expected, $this->planToArray($plan));

        // Move the customer to the next step should produce no chasing steps
        $customer->next_chase_step = (int) $steps[1]->id();
        $customer->saveOrFail();
        $plan = $planner->plan($cadence);
        $this->assertEquals([], $this->planToArray($plan));

        // Move the customer to the final step should produce no chasing steps
        $customer->next_chase_step = (int) $steps[2]->id();
        $customer->saveOrFail();
        $plan = $planner->plan($cadence);
        $this->assertEquals([], $this->planToArray($plan));

        // Removing the next step should produce no chasing steps
        $customer->next_chase_step = null;
        $customer->saveOrFail();
        $plan = $planner->plan($cadence);
        $this->assertEquals([], $this->planToArray($plan));
    }

    public function testPlanInstallment(): void
    {
        $cadence = new ChasingCadence();
        $cadence->name = 'Test';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'Step A',
                'schedule' => 'age:23',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Step B',
                'schedule' => 'age:28',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Step C',
                'schedule' => 'past_due_age:4',
                'action' => ChasingCadenceStep::ACTION_PHONE,
            ],
            [
                'name' => 'Step D',
                'action' => ChasingCadenceStep::ACTION_MAIL,
                'schedule' => 'past_due_age:5',
            ],
            [
                'name' => 'Step E',
                'action' => ChasingCadenceStep::ACTION_ESCALATE,
                'schedule' => 'past_due_age:7',
            ],
        ];
        $cadence->saveOrFail();

        $steps = $cadence->getSteps();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->chasing_cadence_id = (int) $cadence->id();
        $customer->next_chase_step = (int) $steps[0]->id();
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = strtotime('-55 days');
        $invoice->items = [['unit_cost' => 300]];
        $invoice->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = $invoice->date;
        $installment1->amount = 100;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('-25 days');
        $installment2->amount = 100;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+5 days');
        $installment3->amount = 100;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) $invoice->id();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];
        $invoice->attachPaymentPlan($paymentPlan, false, true);

        $planner = $this->getPlanner();
        $plan = $planner->plan($cadence);
        $events = iterator_to_array($plan);

        // 4 actions should be scheduled
        $this->assertCount(4, $events);
        $this->assertEquals('Step B', $events[3]->getStep()->name);
        $this->assertNull($events[3]->getNextStep());

        $this->assertEquals('Step C', $events[2]->getStep()->name);
        $this->assertNull($events[2]->getNextStep());

        $this->assertEquals('Step D', $events[1]->getStep()->name);
        $this->assertNull($events[1]->getNextStep());

        $this->assertEquals('Step E', $events[0]->getStep()->name);
        $this->assertNull($events[0]->getNextStep());
    }

    public function testInvd1859(): void
    {
        $cadence = new ChasingCadence();
        $cadence->name = 'Test';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'Step A',
                'schedule' => 'age:10',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Step B',
                'schedule' => 'age:120',
                'action' => ChasingCadenceStep::ACTION_ESCALATE,
            ],
            [
                'name' => 'Step C',
                'schedule' => 'age:620',
                'action' => ChasingCadenceStep::ACTION_SMS,
                'sms_template_id' => self::$smsTemplate->id(),
            ],
            [
                'name' => 'Step D',
                'action' => ChasingCadenceStep::ACTION_PHONE,
                'schedule' => 'past_due_age:45',
            ],
        ];
        $cadence->saveOrFail();

        $steps = $cadence->getSteps();

        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->chasing_cadence_id = (int) $cadence->id();
        $customer->next_chase_step = (int) $steps[0]->id();
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = strtotime('-150 days');
        $invoice->due_date = strtotime('-150 days');
        $invoice->items = [['unit_cost' => 2000]];
        $invoice->saveOrFail();

        $planner = $this->getPlanner();
        $plan = $planner->plan($cadence);
        $events = iterator_to_array($plan);

        // 3 actions should be scheduled
        $this->assertCount(3, $events);
        $this->assertEquals('Step A', $events[2]->getStep()->name);
        $this->assertEquals('Step C', $events[2]->getNextStep()->name); /* @phpstan-ignore-line */

        $this->assertEquals('Step B', $events[1]->getStep()->name);
        $this->assertEquals('Step C', $events[1]->getNextStep()->name); /* @phpstan-ignore-line */

        $this->assertEquals('Step D', $events[0]->getStep()->name);
        $this->assertNull($events[0]->getNextStep());

        // 1 action should be scheduled at third step
        $customer->next_chase_step = (int) $steps[2]->id();
        $customer->saveOrFail();

        $plan = $planner->plan($cadence);
        $events = [];
        foreach ($plan as $event) {
            // simulate changing the customer next step during iteration
            $customer = $event->getCustomer();
            $customer->next_chase_step = null;
            $customer->saveOrFail();
            $events[] = $event;
        }

        $this->assertCount(1, $events);
        $this->assertEquals('Step D', $events[0]->getStep()->name);
        $this->assertNull($events[0]->getNextStep());
    }

    private function planToArray(iterable $actionPlan): array
    {
        $result = [];
        foreach ($actionPlan as $event) {
            $nextStep = $event->getNextStep();
            $result[] = [
                'customer' => $event->getCustomer()->id(),
                'step' => $event->getStep()->name,
                'nextStep' => $nextStep ? $nextStep->name : null,
            ];
        }

        return $result;
    }
}
