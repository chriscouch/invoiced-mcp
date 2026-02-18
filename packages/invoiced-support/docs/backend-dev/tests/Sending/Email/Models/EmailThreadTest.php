<?php

namespace App\Tests\Sending\Email\Models;

use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Notifications\Libs\NotificationSpoolFacade;
use App\Sending\Email\Models\EmailThread;
use App\Tests\AppTestCase;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;

class EmailThreadTest extends AppTestCase
{
    private static EmailThread $thread2;
    private static Model $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasInbox();
        self::hasCustomer();

        self::$requester = ACLModelRequester::get();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ACLModelRequester::set(self::$requester);
    }

    public function testCreate(): void
    {
        self::$thread = new EmailThread();
        self::$thread->inbox = self::$inbox;
        self::$thread->name = 'test';
        $this->assertTrue(self::$thread->save());
        $this->assertEquals(self::$company->id(), self::$thread->tenant_id);

        self::$thread2 = new EmailThread();
        self::$thread2->inbox = self::$inbox;
        self::$thread2->name = 'test2';
        self::$thread2->customer = self::$customer;
        $this->assertTrue(self::$thread2->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$thread->id(),
            'assignee_id' => null,
            'close_date' => null,
            'customer_id' => null,
            'vendor_id' => null,
            'inbox_id' => self::$inbox->id(),
            'name' => 'test',
            'related_to_id' => null,
            'status' => 'open',
            'object_type' => null,
            'created_at' => self::$thread->created_at,
            'updated_at' => self::$thread->updated_at,
        ];

        $this->assertEquals($expected, self::$thread->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $threads = EmailThread::all();

        $this->assertCount(2, $threads);
        $this->assertEquals(self::$thread2->id(), $threads[0]->id());
        $this->assertEquals(self::$thread->id(), $threads[1]->id());
    }

    /**
     * @depends testCreate
     */
    public function testQueryCustomFieldRestriction(): void
    {
        $member = new Member();
        $member->role = Role::ADMINISTRATOR;
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];

        ACLModelRequester::set($member);

        $this->assertEquals(0, EmailThread::count());

        // update the customer territory
        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();

        $threads = EmailThread::all();
        $this->assertCount(1, $threads);
        $this->assertEquals(self::$thread2->id(), $threads[0]->id());
    }

    public function testNotification(): void
    {
        /** @var Member $member */
        $member = Member::query()->oneOrNull();
        $spool = \Mockery::mock(NotificationSpool::class);
        NotificationSpoolFacade::set($spool);
        $spool->shouldNotReceive('spool');
        self::hasEmailThread();

        self::$thread->customer = self::$customer;
        self::$thread->saveOrFail();

        $spool->shouldReceive('spool')->withArgs([NotificationEventType::ThreadAssigned, self::$company->id, self::$thread->id, $member->id])->once();
        self::$thread->assignee_id = $member->user_id;
        self::$thread->saveOrFail();

        $spool->shouldNotReceive('spool');
        self::$thread->assignee_id = null;
        self::$thread->saveOrFail();
    }
}
