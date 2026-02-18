<?php

namespace App\Tests\Chasing\Models;

use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Notifications\Libs\NotificationSpoolFacade;
use App\Tests\AppTestCase;

class TaskTest extends AppTestCase
{
    private static Task $task;
    private static Model $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        self::$requester = ACLModelRequester::get();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ACLModelRequester::set(self::$requester);
    }

    public function testEventAssociations(): void
    {
        $task = new Task();
        $task->customer_id = 1234;

        $this->assertEquals([
            ['customer', 1234],
        ], $task->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $task = new Task();
        $task->customer = self::$customer;

        $this->assertEquals(array_merge($task->toArray(), [
            'customer' => ModelNormalizer::toArray(self::$customer),
            'bill' => null,
            'vendor_credit' => null,
        ]), $task->getEventObject());
    }

    public function testCannotCreateInvalidRelationships(): void
    {
        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        $task->customer_id = -1;
        $task->user_id = -1;
        $task->completed_by_user_id = -1;
        $this->assertFalse($task->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$task = new Task();
        self::$task->name = 'Send shut off notice';
        self::$task->action = 'mail';
        self::$task->due_date = time();
        self::$task->customer = self::$customer;
        self::$task->saveOrFail();

        $this->assertEquals(self::$company->id(), self::$task->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$task, EventType::TaskCreated);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $tasks = Task::all();

        $this->assertCount(1, $tasks);
    }

    /**
     * @depends testCreate
     */
    public function testQueryCustomFieldRestriction(): void
    {
        $member = new Member();
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $this->assertEquals(0, Task::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        $tasks = Task::all();
        $this->assertCount(1, $tasks);
        $this->assertEquals(self::$task->id(), $tasks[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$task->name = 'Test task';
        $this->assertTrue(self::$task->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$task, EventType::TaskUpdated);
    }

    /**
     * @depends testCreate
     */
    public function testComplete(): void
    {
        EventSpool::enable();

        self::$task->complete = true;
        $this->assertTrue(self::$task->save());
        $this->assertGreaterThan(0, self::$task->completed_date);

        $this->assertHasEvent(self::$task, EventType::TaskCompleted);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$task->id,
            'customer_id' => self::$customer->id(),
            'name' => 'Test task',
            'action' => 'mail',
            'due_date' => self::$task->due_date,
            'user_id' => null,
            'chase_step_id' => null,
            'complete' => true,
            'completed_date' => self::$task->completed_date,
            'completed_by_user_id' => null,
            'created_at' => self::$task->created_at,
            'updated_at' => self::$task->updated_at,
            'bill_id' => null,
            'vendor_credit_id' => null,
        ];

        $this->assertEquals($expected, self::$task->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        EventSpool::enable();

        $this->assertTrue(self::$task->delete());
    }

    /**
     * @depends testDelete
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$task, EventType::TaskDeleted);
    }

    public function testNotification(): void
    {
        self::hasCustomer();
        /** @var Member $member */
        $member = Member::query()->oneOrNull();
        $spool = \Mockery::mock(NotificationSpool::class);
        NotificationSpoolFacade::set($spool);

        $spool->shouldReceive('spool')->withSomeOfArgs(NotificationEventType::TaskAssigned, self::$company->id, $member->id)->once();
        $task = new Task();
        $task->name = 'Send shut off notice';
        $task->action = 'mail';
        $task->due_date = time();
        $task->customer = self::$customer;
        $task->setUser($member->user());
        $task->saveOrFail();

        $spool->shouldNotReceive('spool');
        $task->setUser(null);
        $task->saveOrFail();
    }

    public function testAssignmentPermissions(): void
    {
        $newUser = new User();
        $newUser->email = uniqid().'@example.com';
        $newUser->password = ['gg7WEZ}cgN4FyFk', 'gg7WEZ}cgN4FyFk']; /* @phpstan-ignore-line */
        $newUser->first_name = 'John';
        $newUser->ip = '127.0.0.1';
        $newUser->saveOrFail();

        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser($newUser);
        $member->restriction_mode = Member::OWNER_RESTRICTION;
        $member->saveOrFail();

        // cannot assign to user that cannot see customer
        $task = new Task();
        $task->name = 'Testing permissions';
        $task->action = 'email';
        $task->due_date = time();
        $task->customer = self::$customer;
        $task->setUser($newUser);
        $this->assertFalse($task->save());
        $this->assertEquals('The task cannot be assigned to this user because they do not have permission to see this customer.', (string) $task->getErrors());

        // can assign to no user or to user that can see customer
        $task->setUser(null);
        $this->assertTrue($task->save());

        $task->setUser(self::getService('test.user_context')->get());
        $this->assertTrue($task->save());
    }
}
