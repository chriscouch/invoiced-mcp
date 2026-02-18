<?php

namespace App\Tests\Notifications;

use App\CashApplication\Models\Payment;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Queue\Queue;
use App\Core\Utils\Enums\ObjectType;
use App\EntryPoint\QueueJob\NotificationJob;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Notifications\Emitters\EmailEmitter;
use App\Notifications\Emitters\NullEmitter;
use App\Notifications\Emitters\SlackEmitter;
use App\Notifications\Models\Notification;
use App\Notifications\ValueObjects\Condition;
use App\Notifications\ValueObjects\Evaluate;
use App\Notifications\ValueObjects\Rule;
use App\Tests\AppTestCase;
use Mockery;

class NotificationJobTest extends AppTestCase
{
    private static User $user;

    public static function setUpBeforeClass(): void
    {
        self::$user = self::getService('test.user_context')->get();

        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::getService('test.database')->delete('Users', ['email' => 'notif.test@example.com']);
    }

    protected function tearDown(): void
    {
        self::getService('test.tenant')->set(self::$company);
    }

    private function getJob(): NotificationJob
    {
        return self::getService('test.notification_job');
    }

    public function testGetEmitter(): void
    {
        $job = $this->getJob();

        $this->assertInstanceOf(EmailEmitter::class, $job->getEmitter(Notification::EMITTER_EMAIL));
        $this->assertInstanceOf(SlackEmitter::class, $job->getEmitter(Notification::EMITTER_SLACK));
        $this->assertInstanceOf(NullEmitter::class, $job->getEmitter(Notification::EMITTER_NULL));
    }

    public function testCannotSendTemporaryUser(): void
    {
        // create a temporary user
        $tempUser = self::getService('test.user_registration')->createTemporaryUser([
            'email' => 'notif.test@example.com',
        ]);

        // setup a notification
        $notification = new Notification();
        $notification->setRelation('user_id', $tempUser);

        // setup an event
        $event = new Event();
        $event->type = EventType::InvoicePaid->value;
        $event->object_type_id = ObjectType::Invoice->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];

        // and send the notification
        $job = $this->getJob();
        $this->assertFalse($job->canSend($notification, $event));
    }

    public function testCannotSendUserIdConstraint(): void
    {
        // setup a notification
        $notification = new Notification();
        $notification->setRelation('user_id', self::$user);

        // setup an event
        $event = new Event();
        $event->type = EventType::InvoicePaid->value;
        $event->object_type_id = ObjectType::Invoice->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];
        $event->user_id = -3; // api user

        // and send the notification
        $job = $this->getJob();
        $this->assertFalse($job->canSend($notification, $event));
    }

    public function testCanSendUserIdConstraint(): void
    {
        // setup a notification
        $conditions = [
            new Condition('object.ip', Evaluate::OPERATOR_EQUAL, '10.0.0.1', false),
        ];

        $notification = new Notification();
        $notification->setRelation('user_id', self::$user);
        $notification->match_mode = Rule::MATCH_ANY;
        $notification->conditions = (string) json_encode($conditions);

        // setup an event
        $event = new Event();
        $event->type = EventType::InvoicePaid->value;
        $event->object_type_id = ObjectType::Invoice->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];
        $event->user_id = -3; // api user

        // and send the notification
        $job = $this->getJob();
        $this->assertTrue($job->canSend($notification, $event));
    }

    public function testCanSendCustomFieldRestrictions(): void
    {
        // setup a notification
        $notification = new Notification();
        $notification->user_id = (int) self::$user->id();
        $notification->setRelation('user_id', self::$user);
        $notification->tenant_id = (int) self::$company->id();

        // add restrictions to the user
        $member = Member::where('user_id', self::$user->id())->one();
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];
        $member->notifications = false;
        $member->saveOrFail();

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        // setup an event
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::InvoiceViewed->value;
        $event->object_type_id = ObjectType::Invoice->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];
        $event->setAssociations(['customer' => self::$customer->id()]);

        // and send the notification
        $job = $this->getJob();
        $this->assertTrue($job->canSend($notification, $event));
    }

    /**
     * @depends testCanSendCustomFieldRestrictions
     */
    public function testCannotSendCustomFieldRestrictions(): void
    {
        // setup a notification
        $notification = new Notification();
        $notification->user_id = (int) self::$user->id();
        $notification->setRelation('user_id', self::$user);
        $notification->tenant_id = (int) self::$company->id();

        // make the user an account manager
        $member = Member::where('user_id', self::$user->id())->one();
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];
        $member->saveOrFail();

        // setup an event
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::PaymentCreated->value;
        $event->object_type_id = ObjectType::Payment->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];
        $event->setAssociations(['customer' => -2]);

        // and send the notification
        $job = $this->getJob();
        $this->assertFalse($job->canSend($notification, $event));
    }

    public function testCanSendWithConditions(): void
    {
        // setup notification conditions
        $conditions = [
            new Condition('object.test', Evaluate::OPERATOR_EQUAL, 'value', false),
            new Condition('object.user_agent', Evaluate::OPERATOR_DOES_NOT_EQUAL, 'previous.test'),
        ];

        // setup a notification
        $notification = new Notification();
        $notification->match_mode = Rule::MATCH_ANY;
        $notification->conditions = (string) json_encode($conditions);

        // setup an event
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::PaymentCreated->value;
        $event->object_type_id = ObjectType::Payment->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
            'test' => 'value',
        ];
        $event->previous = (object) [
            'test' => 'another value',
        ];
        $event->setAssociations(['customer' => -2]);

        // and send the notification
        $job = $this->getJob();
        $this->assertTrue($job->canSend($notification, $event));
    }

    public function testCanSendFailingCondition(): void
    {
        // setup notification conditions
        $conditions = [
            new Condition('object.test', Evaluate::OPERATOR_EQUAL, 'value', false),
            new Condition('object.user_agent', Evaluate::OPERATOR_EQUAL, 'blah', false),
            new Condition('object.not_set', Evaluate::OPERATOR_IS_SET),
        ];

        // setup a notification
        $notification = new Notification();
        $notification->match_mode = Rule::MATCH_ALL;
        $notification->conditions = (string) json_encode($conditions);

        // setup an event
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::PaymentCreated->value;
        $event->object_type_id = ObjectType::Payment->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
            'test' => 'value',
        ];
        $event->setAssociations(['customer' => -2]);

        // and send the notification
        $job = $this->getJob();
        $this->assertFalse($job->canSend($notification, $event));
    }

    public function testCanSendWithValidPayment(): void
    {
        // setup notification conditions
        $conditions = [
            new Condition('object.test', Evaluate::OPERATOR_EQUAL, 'value', false),
            new Condition('object.user_agent', Evaluate::OPERATOR_DOES_NOT_EQUAL, 'previous.test'),
        ];

        // setup a notification
        $notification = new Notification();
        $notification->match_mode = Rule::MATCH_ANY;
        $notification->conditions = (string) json_encode($conditions);

        // setup an event
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::PaymentCreated->value;
        $event->object_type_id = ObjectType::Payment->value;
        $event->object = (object) [
            'amount' => 100,
        ];
        $event->previous = (object) [
            'test' => 'another value',
        ];
        $event->setAssociations(['customer' => -2]);

        // and send the notification
        $job = $this->getJob();
        $this->assertTrue($job->canSend($notification, $event));
    }

    public function testSend(): void
    {
        // setup an event
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::PaymentCreated->value;
        $event->object_type_id = ObjectType::Payment->value;
        $event->object = (object) [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];

        // and send it explicitly
        $job = $this->getJob();
        $this->assertTrue($job->send($event, Notification::EMITTER_EMAIL, self::$user));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPerform(): void
    {
        // setup an event
        $obj = [
            'user_agent' => 'blah',
            'ip' => '10.0.0.1',
        ];
        $payment = new Payment(['id' => 'test']);
        $payment->tenant_id = (int) self::$company->id();
        $pendingEvent = new PendingEvent($payment, EventType::PaymentCreated, $obj);
        self::getService('test.event_writer')->write([$pendingEvent]);

        $event = Event::where('type_id', EventType::PaymentCreated->toInteger())->one();
        $job = $this->getJob();
        $job->args = [
            'queued_at' => time(),
            'eventId' => $event->id(),
            'medium' => Notification::EMITTER_EMAIL,
            'userId' => self::$user->id(),
        ];
        $job->perform();
    }

    public function testQueue(): void
    {
        $notification = new Notification(['id' => 100]);
        $notification->user_id = 102;
        $notification->medium = Notification::EMITTER_EMAIL;
        $event = new Event(['id' => 101]);

        // mock queueing operations
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')
            ->andReturnUsing(function ($class, $args) {
                $this->assertEquals(NotificationJob::class, $class);
                $this->assertEquals(102, $args['userId']);
                $this->assertEquals(Notification::EMITTER_EMAIL, $args['medium']);
                $this->assertEquals(101, $args['eventId']);
            })
            ->once();

        $job = $this->getJob();
        $job->setJobQueue($queue);
        $job->queue($notification, $event);
    }
}
