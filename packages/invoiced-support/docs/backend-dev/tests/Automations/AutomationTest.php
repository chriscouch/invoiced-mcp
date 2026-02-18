<?php

namespace App\Tests\Automations;

use App\AccountsReceivable\Models\Customer;
use App\Automations\Enums\AutomationActionType;
use App\Automations\Enums\AutomationEventType;
use App\Automations\Enums\AutomationTriggerType;
use App\Automations\EventSubscriber\AutomationSubscriber;
use App\Automations\Models\AutomationRun;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowStep;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\Models\AutomationWorkflowVersion;
use App\Automations\ValueObjects\AutomationEvent;
use App\Chasing\Models\Task;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\Enums\ObjectType;
use App\EntryPoint\CronJob\AutomationJob;
use App\EntryPoint\QueueJob\AutomationQueueJob;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Mockery;
use App\Core\Orm\Exception\ModelException;

class AutomationTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function setUp(): void
    {
        parent::setUp();
        AutomationSubscriber::enable();
        EventSpool::enable();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        AutomationSubscriber::disable();
        EventSpool::disable();
    }

    public function testManualTrigger(): void
    {
        $version = $this->makeWorkflow(ObjectType::Customer,
            [
                'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
            ],
            null, AutomationTriggerType::Manual);

        $customer = new Customer();
        $customer->name = 'Manual Trigger Automations';
        $customer->saveOrFail();

        self::getService('test.trigger_automation')->initiate($version->automation_workflow, $customer, AutomationTriggerType::Manual);
        $this->finishAllWorkflows($version);

        $this->assertEquals('Hello World', $customer->refresh()->notes);
    }

    public function testEventTrigger(): void
    {
        $version = $this->makeWorkflow(ObjectType::Customer,
            [
                'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
            ],
            EventType::CustomerCreated->toInteger());

        $customer = new Customer();
        $customer->name = 'Event Automations';
        $customer->saveOrFail();

        self::getService('test.event_spool')->flush();
        $this->finishAllWorkflows($version);

        $this->assertEquals('Hello World', $customer->refresh()->notes);
    }

    /**
     * @depends testEventTrigger
     */
    public function testDeleteEventTrigger(): void
    {
        self::hasCustomer();

        $version = $this->makeWorkflow(
            objectType: ObjectType::Customer,
            params: [
                'fields' => [
                    (object) [
                        'name' => 'customer',
                        'value' => self::$customer->id,
                    ],
                    (object) [
                        'name' => 'due_date',
                        'value' => 7,
                    ],
                    (object) [
                        'name' => 'name',
                        'value' => '{{customer.id}}',
                    ],
                    (object) [
                        'name' => 'action',
                        'value' => 'email',
                    ],
                ],
                'object_type' => 'task',
            ],
            eventType: EventType::CustomerDeleted->toInteger(),
            actionType: AutomationActionType::CreateObject,
        );

        $customer = Customer::where('name', 'Event Automations')->one();
        $customerId = $customer->id();
        $customer->delete();

        self::getService('test.event_spool')->flush();
        $this->finishAllWorkflows($version);

        $tasks = Task::where('customer_id', self::$customer)->execute();
        $this->assertCount(1, $tasks);
        $this->assertEquals($customerId, $tasks[0]->name);
    }

    public function testScheduledTriggerInvalid(): void
    {
        try {
            $this->makeWorkflow(ObjectType::Customer,
                [
                    'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
                ],
                null, AutomationTriggerType::Schedule);
            $this->assertTrue(false, 'Expected ModelException');
        } catch (ModelException $e) {
            $this->assertEquals('Failed to save AutomationWorkflowTrigger: Missing Recurrence Rule for Schedule Trigger', $e->getMessage());
        }

        try {
            $this->makeWorkflow(ObjectType::Customer,
                [
                    'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
                ],
                null, AutomationTriggerType::Schedule, 'FREQ=test;INTERVAL=2;COUNT=4');
            $this->assertTrue(false, 'Expected ModelException');
        } catch (ModelException $e) {
            $this->assertEquals('Invalid Recurrence Rule', $e->getMessage());
        }
    }

    public function testScheduledTriggerSave(): void
    {
        $date = CarbonImmutable::now()->startOfHour();
        $version = $this->makeWorkflow(ObjectType::Customer,
            [
                'value' => 'Hello World', 'name' => 'notes',
            ],
            null, AutomationTriggerType::Schedule, 'RRULE:FREQ=DAILY;INTERVAL=1;COUNT=4');

        $trigger = $version->triggers[0];
        $this->assertTrue($date->addDay()->isSameSecond($trigger->getNextRun()));
        $this->assertNull($trigger->last_run);

        $version = $this->makeWorkflow(ObjectType::Customer,
            [
                'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
            ],
            null, AutomationTriggerType::Schedule, 'RRULE:FREQ=HOURLY;INTERVAL=1;COUNT=4');
        $trigger = $version->triggers[0];
        $this->assertTrue($date->addHour()->isSameSecond($trigger->getNextRun()));
        $this->assertNull($trigger->last_run);
    }

    /**
     * @dataProvider rRuleProvider
     */
    public function testAdvance(string $rrule, CarbonImmutable $date): void
    {
        $version = $this->makeWorkflow(ObjectType::Customer,
            [
                'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
            ],
            null, AutomationTriggerType::Schedule, $rrule);

        $trigger = $version->triggers[0];

        $this->assertTrue($date->isSameSecond($trigger->getNextRun()));
        $this->assertNull($trigger->last_run);

        $trigger->advance();
        $this->assertTrue($date->addDays(2)->isSameSecond($trigger->getNextRun()));
        $this->assertTrue($date->isSameSecond($trigger->last_run));

        $next = $date->addDays(2);
        $trigger->advance();
        $this->assertTrue($date->addDays(4)->isSameSecond($trigger->getNextRun()));
        $this->assertTrue($next->isSameSecond($trigger->last_run));
    }

    public function testAdvanceHours(): void
    {
        $date = CarbonImmutable::now()->addHours(2)->startOfHour();
        $version = $this->makeWorkflow(ObjectType::Customer,
            [
                'value' => 'Hello World', 'name' => 'notes', 'object_type' => 'customer',
            ],
            null, AutomationTriggerType::Schedule, 'RRULE:FREQ=HOURLY;INTERVAL=2');

        $trigger = $version->triggers[0];

        $this->assertTrue($date->isSameSecond($trigger->getNextRun()));
        $this->assertNull($trigger->last_run);

        $trigger->advance();
        $this->assertTrue($date->addHours(2)->isSameSecond($trigger->getNextRun()));
        $this->assertTrue($date->isSameSecond($trigger->last_run));

        $next = $date->addHours(2);
        $trigger->advance();
        $this->assertTrue($date->addHours(4)->isSameSecond($trigger->getNextRun()));
        $this->assertTrue($next->isSameSecond($trigger->last_run));
    }

    public function testCustomEventDispatch(): void
    {
        $version = $this->makeWorkflow(ObjectType::InboxEmail,
            [
                'value' => 'Hello World', 'name' => 'subject', 'object_type' => 'inbox_email',
            ],
            AutomationEventType::ReceivedEmail->toInteger());

        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail();

        self::getService('test.event_dispatcher_interface')->dispatch(new AutomationEvent(self::$inboxEmail, AutomationEventType::ReceivedEmail->toInteger()), 'automation_event.dispatch');
        $this->finishAllWorkflows($version);

        $this->assertEquals('Hello World', self::$inboxEmail->refresh()->subject);
    }

    public function testCronJob(): void
    {
        /** @var Connection $database */
        $database = self::getService('test.database');
        $database->delete('AutomationWorkflows', ['1' => 1]); /* @phpstan-ignore-line */

        $company1 = self::getTestDataFactory()->createCompany();
        $company2 = self::getTestDataFactory()->createCompany();

        self::getService('test.tenant')->set($company1);
        // will run
        $workflow1 = $this->createWorkflow(ObjectType::Customer);
        $version11 = $this->createWorkflowVersion($workflow1);
        $workflow1->current_version = $version11;
        $workflow1->saveOrFail();
        $trigger11 = $this->createTriggerToRun($version11);
        $trigger12 = $this->createTriggerToRun($version11);

        // will not run
        $this->createWorkflowTrigger($version11, AutomationTriggerType::Schedule, null, 'RRULE:FREQ=HOURLY;INTERVAL=1;COUNT=4');
        $this->createWorkflowTrigger($version11, AutomationTriggerType::Manual, null, 'RRULE:FREQ=HOURLY;INTERVAL=1;COUNT=4');
        $trigger = $this->createTriggerToRun($version11);
        $trigger->next_run = CarbonImmutable::now()->addDay();
        $trigger->saveOrFail();
        $this->createWorkflowTrigger($version11, AutomationTriggerType::Manual, null, 'RRULE:FREQ=HOURLY;INTERVAL=1;COUNT=4');
        $trigger = $this->createTriggerToRun($version11);
        $trigger->next_run = null;
        $trigger->saveOrFail();

        // will not run
        $version12 = $this->createWorkflowVersion($workflow1, 2);
        $this->createTriggerToRun($version12);

        // will not run
        $workflow = $this->createWorkflow(ObjectType::Customer);
        $workflow->deleted = true;
        $workflow->saveOrFail();
        $version = $this->createWorkflowVersion($workflow);
        $this->createTriggerToRun($version);
        $workflow = $this->createWorkflow(ObjectType::Customer);
        $workflow->enabled = false;
        $workflow->saveOrFail();
        $version = $this->createWorkflowVersion($workflow);
        $this->createTriggerToRun($version);

        // another tenant to run
        self::getService('test.tenant')->set($company2);
        $workflow2 = $this->createWorkflow(ObjectType::Customer);
        $version2 = $this->createWorkflowVersion($workflow2);
        $workflow2->current_version = $version2;
        $workflow2->saveOrFail();
        $trigger2 = $this->createTriggerToRun($version2);

        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')->withArgs([AutomationQueueJob::class, [
            'tenant_id' => $company1->id,
            'trigger_id' => $trigger11->id,
            'workflow_id' => $workflow1->id,
        ]])->once();
        $queue->shouldReceive('enqueue')->withArgs([AutomationQueueJob::class, [
            'tenant_id' => $company1->id,
            'trigger_id' => $trigger12->id,
            'workflow_id' => $workflow1->id,
        ]])->once();
        $queue->shouldReceive('enqueue')->withArgs([AutomationQueueJob::class, [
            'tenant_id' => $company2->id,
            'trigger_id' => $trigger2->id,
            'workflow_id' => $workflow2->id,
        ]])->once();
        $run = Mockery::mock(Run::class);
        $run->shouldReceive('writeOutput')->withArgs(['Automation initiated for 3 triggers'])->once();
        $automationJob = new AutomationJob($queue, $database);
        $automationJob->setStatsd(new StatsdClient());
        $automationJob->execute($run);

        self::getService('test.tenant')->set(self::$company);
        $company1->delete();
        $company2->delete();
    }

    private function createTriggerToRun(AutomationWorkflowVersion $version): AutomationWorkflowTrigger
    {
        $trigger = $this->createWorkflowTrigger($version, AutomationTriggerType::Schedule, null, 'RRULE:FREQ=HOURLY;INTERVAL=1;COUNT=4');
        $trigger->next_run = CarbonImmutable::now()->subDay();
        $trigger->saveOrFail();

        return $trigger;
    }

    private function createWorkflow(ObjectType $objectType, bool $enabled = true): AutomationWorkflow
    {
        $workflow = new AutomationWorkflow();
        $workflow->name = 'Event Trigger '.uniqid();
        $workflow->object_type = $objectType;
        $workflow->enabled = $enabled;
        $workflow->saveOrFail();

        return $workflow;
    }

    private function createWorkflowVersion(AutomationWorkflow $workflow, int $v = 1): AutomationWorkflowVersion
    {
        $version = new AutomationWorkflowVersion();
        $version->automation_workflow = $workflow;
        $version->version = $v;
        $version->saveOrFail();

        return $version;
    }

    private function createWorkflowTrigger(AutomationWorkflowVersion $version, AutomationTriggerType $triggerType = AutomationTriggerType::Event, int $eventType = null, string $rRule = null): AutomationWorkflowTrigger
    {
        $trigger = new AutomationWorkflowTrigger();
        $trigger->workflow_version = $version;
        $trigger->trigger_type = $triggerType;
        if ($eventType) {
            $trigger->event_type = $eventType;
        }
        $trigger->r_rule = $rRule;
        $trigger->saveOrFail();

        return $trigger;
    }

    private function makeWorkflow(ObjectType $objectType, array $params, int $eventType = null, AutomationTriggerType $triggerType = AutomationTriggerType::Event, string $rRule = null, AutomationActionType $actionType = AutomationActionType::ModifyPropertyValue): AutomationWorkflowVersion
    {
        $workflow = $this->createWorkflow($objectType);
        $version = $this->createWorkflowVersion($workflow);
        $this->createWorkflowTrigger($version, $triggerType, $eventType, $rRule);

        $step = new AutomationWorkflowStep();
        $step->order = 1;
        $step->workflow_version = $version;
        $step->action_type = $actionType;
        $step->settings = (object) $params;
        $step->saveOrFail();

        $workflow->current_version = $version;
        $workflow->enabled = true;
        $workflow->saveOrFail();

        return $version;
    }

    private function finishAllWorkflows(AutomationWorkflowVersion $version): void
    {
        $runs = AutomationRun::where('workflow_version_id', $version)->all();
        foreach ($runs as $run) {
            self::getService('test.workflow_runner')->start($run);
        }
    }

    public function rRuleProvider(): array
    {
        return [
            [
                'RRULE:FREQ=DAILY;INTERVAL=2;BYHOUR=0',
                CarbonImmutable::parse('next 00:00')->addDay(),
            ],
            [
                'RRULE:FREQ=DAILY;INTERVAL=2',
                CarbonImmutable::now()->addDays(2)->startOfHour(),
            ],
        ];
    }
}
