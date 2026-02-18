<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\CustomerChasing\CustomerChasingRun;
use App\Chasing\Enums\ChasingChannelEnum;
use App\Chasing\Enums\ChasingTypeEnum;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\ChasingStatistic;
use App\Chasing\Models\CompletedChasingStep;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Sms\Models\SmsTemplate;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Traversable;

class ChasingRunTest extends AppTestCase
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
        self::hasInvoice();

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
            // should not fire
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

        $invoice2 = new Invoice();
        $invoice2->date = strtotime('-8 days');
        $invoice2->setCustomer(self::$customer2);
        $invoice2->currency = 'eur';
        $invoice2->items = [['unit_cost' => 100]];
        $invoice2->saveOrFail();

        self::$customer3 = new Customer();
        self::$customer3->name = 'Finished Run';
        self::$customer3->country = 'US';
        self::$customer3->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer3->next_chase_step = null;
        self::$customer3->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->date = strtotime('-180 days');
        $invoice3->setCustomer(self::$customer3);
        $invoice3->items = [['unit_cost' => 100]];
        $invoice3->saveOrFail();

        self::$customer4 = new Customer();
        self::$customer4->name = 'Chasing Paused';
        self::$customer4->country = 'US';
        self::$customer4->chase = false;
        self::$customer4->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer4->next_chase_step = (int) self::$cadence->getSteps()[0]->id();
        self::$customer4->saveOrFail();

        $invoice4 = new Invoice();
        $invoice4->date = strtotime('-180 days');
        $invoice4->setCustomer(self::$customer4);
        $invoice4->items = [['unit_cost' => 100]];
        $invoice4->saveOrFail();
    }

    public function testChase(): void
    {
        ChasingStatistic::queryWithoutMultitenancyUnsafe()->delete();
        $run = $this->getRun(self::$cadence, self::$customer);
        $run->chase(self::$cadence);

        // one step should be skipped as duplicate for customer 1
        /** @var Traversable $steps */
        $steps = $this->getCompletedSteps(self::$customer);
        /** @var CompletedChasingStep[] $completedSteps */
        $completedSteps = iterator_to_array($steps);
        /** @var ChasingStatistic[] $statistics */
        $statistics = ChasingStatistic::where('customer_id', self::$customer->id)->execute();
        /** @var Invoice[] $invoices */
        $invoices = Invoice::where('customer', self::$customer->id)->sort('date ASC')->execute();
        $this->assertCount(6, $steps);
        $this->assertCount(8, $statistics);

        $successfulSteps = array_reverse(array_filter($completedSteps, fn ($step) => $step->successful));
        $channels = [
            ChasingChannelEnum::Escalate,
            ChasingChannelEnum::Escalate,
            ChasingChannelEnum::Phone,
            ChasingChannelEnum::Email,
        ];
        $i = 0;
        foreach ($successfulSteps as $step) {
            $channel = array_shift($channels);
            $this->assertEquals($step->chase_step_id, $statistics[$i]->cadence_step_id);
            $this->assertEquals($invoices[0]->id, $statistics[$i]->invoice_id);
            $this->assertEquals($channel->value, $statistics[$i]->channel);
            ++$i;
            $this->assertEquals($step->chase_step_id, $statistics[$i]->cadence_step_id);
            $this->assertEquals($invoices[1]->id, $statistics[$i]->invoice_id);
            $this->assertEquals($channel->value, $statistics[$i]->channel);
            ++$i;
        }

        $this->assertEquals('Email', $completedSteps[0]->relation('chase_step_id')->name);
        $this->assertEquals('Letter', $completedSteps[1]->relation('chase_step_id')->name);
        $this->assertEquals('Final Notice', $completedSteps[2]->relation('chase_step_id')->name);
        $this->assertEquals('Second Text Notice', $completedSteps[3]->relation('chase_step_id')->name);
        $this->assertEquals('escalation 1', $completedSteps[4]->relation('chase_step_id')->name);
        $this->assertEquals('escalation 2', $completedSteps[5]->relation('chase_step_id')->name);

        // customer 2 should have the first step completed
        $completedSteps = $this->getCompletedSteps(self::$customer2);
        $this->assertCount(1, $completedSteps);
        $this->assertEquals('Email', $completedSteps[0]->relation('chase_step_id')->name); /* @phpstan-ignore-line */

        // customer 3 should not have bee chased
        $this->assertCount(0, $this->getCompletedSteps(self::$customer3));

        // customer 4 should not have bee chased
        $this->assertCount(0, $this->getCompletedSteps(self::$customer4));
    }

    public function testChaseCadenceEdit(): void
    {
        ChasingStatistic::queryWithoutMultitenancyUnsafe()->delete();
        $customer = new Customer();
        $customer->name = 'test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $cadence = new ChasingCadence();
        $cadence->name = 'Test';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'Email',
                'schedule' => 'past_due_age:2',
                'action' => ChasingCadenceStep::ACTION_MAIL,
                'email_template_id' => 'test',
            ],
            [
                'name' => 'Letter',
                'schedule' => 'past_due_age:26',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        $cadence->saveOrFail();
        $cadence->refresh();
        $steps = $cadence->toArray()['steps'];
        array_unshift($steps, [
                'name' => 'Edit',
                'schedule' => 'past_due_age:1',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ]);
        $cadence->steps = $steps;
        $cadence->saveOrFail();

        $cadence->refresh();

        $run = $this->getRun($cadence, $customer);
        $run->chase($cadence);

        $completedSteps = $this->getCompletedSteps($customer, $cadence);
        $this->assertEquals(0, ChasingStatistic::query()->count());

        // one step should be skipped as duplicate
        $this->assertCount(1, $completedSteps);

        $cadence->refresh();
        $customer->refresh();
        $steps = $cadence->getSteps();
        $this->assertEquals($steps[1]->id, $completedSteps[0]->chase_step_id); /* @phpstan-ignore-line */
        $this->assertEquals($steps[2]->id, $customer->next_chase_step_id);
    }

    private function getRun(ChasingCadence $cadence, Customer $customer): CustomerChasingRun
    {
        // add another invoice
        // because initial one was paid
        $invoice = new Invoice();
        $invoice->date = strtotime('-14 days');
        $invoice->due_date = strtotime('-14 days');
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();
        // rewind chasing step
        $customer->chasing_cadence_id = (int) $cadence->id();
        $customer->next_chase_step = (int) $cadence->getSteps()[0]->id();
        $customer->saveOrFail();

        return self::getService('test.chasing_run');
    }

    private function getCompletedSteps(Customer $customer, ChasingCadence $cadence = null): iterable
    {
        $cadence = $cadence ?? self::$cadence;

        return CompletedChasingStep::where('cadence_id', $cadence)
            ->where('customer_id', $customer)
            // we save completed steps in reverse order
            ->sort('id DESC')
            ->all();
    }

    private function createChasingStatistics(Invoice $invoice): ChasingStatistic
    {
        $statistics = new ChasingStatistic();
        $statistics->type = ChasingTypeEnum::Customer->value;
        $statistics->customer_id = self::$customer->id;
        $statistics->invoice_id = $invoice->id;
        $statistics->cadence_id = self::$cadence->id;
        $statistics->cadence_step_id = 1;
        $statistics->channel = ChasingChannelEnum::Email->value;
        $statistics->date = CarbonImmutable::now()->toIso8601String();
        $statistics->saveOrFail();

        return $statistics;
    }

    public function testChaseAttempts(): void
    {
        $cadence = new ChasingCadence();
        $cadence->name = 'Test2';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'Email',
                'schedule' => 'age:7',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
            ],
        ];
        $cadence->saveOrFail();

        self::hasCustomer();
        self::hasInvoice();
        ChasingStatistic::queryWithoutMultitenancyUnsafe()->delete();
        $this->createChasingStatistics(self::$invoice);
        $run = $this->getRun($cadence, self::$customer);
        $run->chase($cadence);
        /** @var ChasingStatistic $statistics */
        $statistics = ChasingStatistic::where('invoice_id', self::$invoice)->execute();
        $this->assertEquals($statistics[0]->attempts, 1);
        $this->assertEquals($statistics[1]->attempts, 2);
    }
}
