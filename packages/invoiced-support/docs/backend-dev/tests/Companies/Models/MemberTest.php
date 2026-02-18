<?php

namespace App\Tests\Companies\Models;

use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\Task;
use App\Companies\Api\MemberFrequencyUpdateApiRoute;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Exception\ListenerException;
use App\Core\RestApi\Models\ApiKey;
use App\ActivityLog\Enums\EventType;
use App\Notifications\Api\ConvertUserNotificationsRoute;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Libs\MigrateV2Notifications;
use App\Notifications\Models\Notification;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Notifications\Models\NotificationEventSetting;
use App\Sending\Email\Models\EmailTemplate;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MemberTest extends AppTestCase
{
    private static User $newUser;
    private static User $newUser2;
    private static Member $member;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // delete user if one already exists at any of these emails
        $deleteEmails = ['test2@example.com', 'test3@example.com', 'test@cud.com', 'switchNotifications@test.com'];
        foreach ($deleteEmails as $email) {
            $user = User::where('email', $email)->oneOrNull();
            if ($user) {
                $user->delete();
            }
        }

        self::hasCompany();
        self::hasCustomer();
        // create a new user
        self::$newUser = self::createUser('test2@example.com');
    }

    public static function createUser(string $email): User
    {
        $newUser = new User();
        $newUser->email = $email;
        $newUser->password = ['gg7WEZ}cgN4FyFk', 'gg7WEZ}cgN4FyFk']; /* @phpstan-ignore-line */
        $newUser->first_name = 'John';
        $newUser->ip = '127.0.0.1';
        $newUser->default_company_id = self::$company->id;
        $newUser->saveOrFail();

        return $newUser;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // delete user if one already exists at any of these emails
        $deleteEmails = ['test2@example.com', 'test3@example.com'];
        foreach ($deleteEmails as $email) {
            $user = User::where('email', $email)->oneOrNull();
            if ($user) {
                $user->delete();
            }
        }
    }

    public function testRole(): void
    {
        $member = new Member();
        $member->tenant_id = (int) self::$company->id();
        $member->role = Role::ADMINISTRATOR;
        $role = $member->role();
        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals(Role::ADMINISTRATOR, $role->id);
        $this->assertEquals(self::$company->id(), $role->tenant_id);
    }

    public function testAllowed(): void
    {
        $member = new Member();
        $member->tenant_id = (int) self::$company->id();
        $member->role = Role::ADMINISTRATOR;

        $this->assertTrue($member->allowed('business.admin'));
        $this->assertTrue($member->allowed('business.billing'));
        $this->assertTrue($member->allowed('catalog.edit'));
        $this->assertTrue($member->allowed('settings.edit'));
        $this->assertTrue($member->allowed('accounts.read'));

        $member->role = 'read_only';
        $this->assertFalse($member->allowed('business.admin'));
        $this->assertFalse($member->allowed('business.billing'));
        $this->assertFalse($member->allowed('catalog.edit'));
        $this->assertFalse($member->allowed('settings.edit'));
        $this->assertTrue($member->allowed('accounts.read'));
    }

    public function testCreateInvalidEmail(): void
    {
        $member = new Member();
        $member->email = 'hey'; /* @phpstan-ignore-line */
        $member->role = Role::ADMINISTRATOR;
        $this->assertFalse($member->save());
    }

    public function testCreateAlreadyMember(): void
    {
        $member = new Member();
        $member->email = self::getService('test.user_context')->get()->email; /* @phpstan-ignore-line */
        $member->role = Role::ADMINISTRATOR;
        $this->assertFalse($member->save());
    }

    public function testCreateInvalidRole(): void
    {
        $member = new Member();
        $member->setUser(self::getService('test.user_context')->get());
        $member->role = 'does_not_exist';
        $this->assertFalse($member->save());
    }

    public function testCreate(): void
    {
        // try adding a member already registered
        $member = new Member();
        $member->email = 'test2@example.com'; /* @phpstan-ignore-line */
        $member->role = Role::ADMINISTRATOR;
        $this->assertTrue($member->save());
        $this->assertEquals(self::$newUser->id(), $member->user_id);

        // verify notification settings were created
        foreach ([
                     NotificationEventType::AutoPayFailed,
                     NotificationEventType::EmailReceived,
                     NotificationEventType::EstimateApproved,
                     NotificationEventType::EstimateViewed,
                     NotificationEventType::InvoiceViewed,
                     NotificationEventType::PaymentDone,
                     NotificationEventType::PaymentLinkCompleted,
                     NotificationEventType::PaymentPlanApproved,
                     NotificationEventType::PromiseCreated,
                     NotificationEventType::SignUpPageCompleted,
                     NotificationEventType::SubscriptionCanceled,
                     NotificationEventType::SubscriptionExpired,
                     NotificationEventType::TaskAssigned,
                     NotificationEventType::ThreadAssigned,
                 ] as $event) {
            $notif = NotificationEventSetting::where('member_id', $member->id())
                ->where('notification_type', $event->toInteger())
                ->oneOrNull();
            $this->assertInstanceOf(NotificationEventSetting::class, $notif);
            $this->assertEquals(NotificationFrequency::Instant, $notif->getFrequency());
        }

        // try adding a member that has not registered yet
        self::$member = new Member();
        self::$member->email = 'test3@example.com'; /* @phpstan-ignore-line */
        self::$member->first_name = 'Test'; /* @phpstan-ignore-line */
        self::$member->last_name = 'User'; /* @phpstan-ignore-line */
        self::$member->role = 'employee';
        $this->assertTrue(self::$member->save());

        // check if a temporary user was created
        self::$newUser2 = User::where('email', 'test3@example.com')->one();
        $this->assertTrue(self::$newUser2->isTemporary());
        $this->assertEquals('Test User', self::$newUser2->name(true));
        $this->assertEquals(self::$company->id(), self::$newUser2->default_company_id);
    }

    public function testToArray(): void
    {
        $expected = [
            'id' => self::$member->id(),
            'user' => self::$newUser2->toArray(),
            'role' => 'employee',
            'last_accessed' => null,
            'restriction_mode' => 'none',
            'restrictions' => null,
            'created_at' => self::$member->created_at,
            'updated_at' => self::$member->updated_at,
            'email_update_frequency' => 'week',
            'notifications' => true,
            'subscribe_all' => true,
            'notification_viewed' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $this->assertEquals($expected, self::$member->toArray());
    }

    public function testEditRole(): void
    {
        // create an api key
        $this->assertInstanceOf(ApiKey::class, self::$company->getProtectedApiKey('test', self::$newUser2));

        self::$member->role = Role::ADMINISTRATOR;
        $this->assertTrue(self::$member->save());

        // should delete any API keys
        $this->assertEquals(0, ApiKey::where('user_id', self::$newUser2->id())->where('protected', true)->count(), 'Should delete API keys after changing member role');
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // create an api key
        $this->assertInstanceOf(ApiKey::class, self::$company->getProtectedApiKey('test', self::$newUser2));

        $this->assertTrue(self::$member->delete());

        // should delete any API keys
        $this->assertEquals(0, ApiKey::where('user_id', self::$newUser2->id())->where('protected', true)->count(), 'Should delete API keys after removing member');
    }

    private function createMember(User $user, string $role = Role::ADMINISTRATOR): Member
    {
        $member = new Member();
        $member->setUser($user);
        $member->role = $role;
        $member->saveOrFail();

        return $member;
    }

    private function checkMemberCreate(AbstractEvent $event): ?string
    {
        try {
            Member::checkQuota($event);

            return null;
        } catch (ListenerException $e) {
            return $e->getMessage();
        }
    }

    public function testCheckQuota(): void
    {
        // clean up the db
        $users = User::where('default_company_id', self::$company->id)
            ->where('created_at', date('Y-m-d 00:00:00'), '>=')
            ->where('created_at', date('Y-m-d 23:59:59'), '<=')
            ->where('id', self::$company->creator()->id, '!=') /* @phpstan-ignore-line */
            ->first(1000);
        foreach ($users as $user) {
            $user->delete();
        }

        // set up defaults
        $users = [];
        $members = [];
        $value = 2;

        self::$company->quota->set(QuotaType::Users, $value);

        // create initial users
        for ($i = 1; $i < $value; ++$i) {
            $users[$i] = self::createUser(time()."checkQuota$i@example.com");
            $members[$i] = $this->createMember($users[$i]);
        }
        $event = new ModelCreated(end($members));

        // test global limit
        $error = $this->checkMemberCreate($event);
        $this->assertStringContainsString('user limit', (string) $error);

        // check daily limit
        $members[1]->delete();
        self::createUser(time().'testCheckQuota@gmail.com');
        $error = $this->checkMemberCreate($event);
        $this->assertStringContainsString('user daily limit', (string) $error);

        // increase the num of users limit
        self::$company->quota->set(QuotaType::Users, $value + 1);
        $error = $this->checkMemberCreate($event);
        $this->assertNull($error);

        // clean up
        for ($i = 1; $i < $value; ++$i) {
            $users[$i]->delete();
        }
    }

    public function testMemberDelete(): void
    {
        self::$company->quota->set(QuotaType::Users, 100);
        $employee = self::createUser(time().'employeedelete@example.com');
        $employeeMember = $this->createMember($employee);

        $task = new Task();
        $task->customer_id = self::$customer->id;
        $task->user_id = $employee->id;
        $task->due_date = time();
        $task->name = 'test';
        $task->action = ChasingCadenceStep::ACTION_ESCALATE;
        $task->saveOrFail();

        $this->assertEquals($employee->id, $task->user_id);

        $emailTemplate = new EmailTemplate();
        $emailTemplate->id = 'test';
        $emailTemplate->type = EmailTemplate::TYPE_CHASING;
        $emailTemplate->name = 'Test';
        $emailTemplate->subject = 'Test';
        $emailTemplate->body = 'Testing...';
        $emailTemplate->saveOrFail();

        $cadence = new ChasingCadence();
        $cadence->name = 'Test';
        $cadence->time_of_day = 7;
        $cadence->steps = [
            [
                'name' => 'Email',
                'schedule' => 'age:7',
                'action' => ChasingCadenceStep::ACTION_EMAIL,
                'email_template_id' => 'test',
                'assigned_user_id' => $employee->id,
            ],
        ];
        $cadence->saveOrFail();
        $step = $cadence->getSteps()[0];

        self::hasCustomer();
        self::$customer->owner = $employee;
        self::$customer->saveOrFail();

        $employeeMember->delete();

        $task->refresh();
        $step->refresh();
        self::$customer->refresh();
        $this->assertEquals(null, $task->user_id);
        $this->assertEquals(null, $step->assigned_user_id);
        $this->assertEquals(null, self::$customer->owner_id);
    }

    public function testCud(): void
    {
        $role = new Role();
        $role->name = 'test';
        $role->saveOrFail();

        $user = self::createUser('test@cud.com');
        $requester = new Member();
        $requester->role = $role->id;
        $requester->setUser($user);
        $requester->saveOrFail();

        $company = new Company();
        $company->id = $requester->tenant_id;
        $this->assertFalse($company->memberCanEdit($requester));

        $role->settings_edit = true;
        $role->saveOrFail();
        $requester = Member::findOrFail($requester->id);

        $company = new Company([
            'id' => $requester->tenant_id,
        ]);
        $company->id = $requester->tenant_id;
        $this->assertTrue($company->memberCanEdit($requester));
    }

    public function testNotificationFlags(): void
    {
        NotificationEventCompanySetting::query()->delete();

        $notificationCompanySettings = new NotificationEventCompanySetting();
        $notificationCompanySettings->notification_type = 1;
        $notificationCompanySettings->frequency = 1;
        $notificationCompanySettings->saveOrFail();

        self::$company->features->disable('notifications_v2_default');
        $user = self::createUser(time().'Notification1@example.com');
        $member = self::createMember($user);
        $this->assertFalse($member->notifications);
        $this->assertEquals(0, NotificationEventSetting::where('member_id', $member->id)->count());
        // cleanup
        self::$company->features->enable('notifications_v2_default');

        $user = self::createUser(time().'Notification4@example.com');
        $member = self::createMember($user);
        $this->assertTrue($member->notifications);
        $botifications = NotificationEventSetting::where('member_id', $member->id)->execute();
        $this->assertCount(1, $botifications);
        $this->assertEquals($notificationCompanySettings->notification_type, $botifications[0]->notification_type);
    }

    public function testConvertMemberTest(): void
    {
        self::$company->features->disable('notifications_v2_default');
        $memberX = ACLModelRequester::get();
        $user = self::createUser(time().'convert1@example.com');
        $member = self::createMember($user);
        $user2 = self::createUser(time().'convert2@example.com');
        $member2 = self::createMember($user2);
        $user3 = self::createUser(time().'convert3@example.com');
        $member3 = self::createMember($user3);
        $user4 = self::createUser(time().'convert4@example.com');
        $member4 = self::createMember($user4);
        $member4->notifications = true;
        $member4->saveOrFail();
        ACLModelRequester::set($member);
        // cleanup
        self::$company->features->disable('notifications_v2_default');

        $request = new Request();
        $database = self::getService('test.database');
        $migrate = new MigrateV2Notifications($database);
        $route = new ConvertUserNotificationsRoute($migrate);

        $database->executeStatement("delete from Notifications where user_id = {$user->id}");
        $database->executeStatement("update Notifications set enabled = 0 where user_id = {$user3->id}");

        $context = self::getService('test.api_runner')->validateRequest($request, $route->getDefinition());
        $route->buildResponse($context);

        $this->checkMemberConversion($member);
        $this->checkMemberConversion($member2);
        $this->checkMemberConversion($member3, [
            NotificationEventType::ThreadAssigned->value => NotificationFrequency::Instant->value,
            NotificationEventType::TaskAssigned->value => NotificationFrequency::Instant->value,
            NotificationEventType::PaymentPlanApproved->value => NotificationFrequency::Instant->value, ]);
        $this->checkMemberConversion($member4, []);
        ACLModelRequester::set($memberX);
    }

    private function checkMemberConversion(Member $member, ?array $expected = null): void
    {
        $expected ??= [
            NotificationEventType::EmailReceived->value => NotificationFrequency::Instant->value,
            NotificationEventType::ThreadAssigned->value => NotificationFrequency::Instant->value,
            NotificationEventType::TaskAssigned->value => NotificationFrequency::Instant->value,
            NotificationEventType::InvoiceViewed->value => NotificationFrequency::Instant->value,
            NotificationEventType::EstimateViewed->value => NotificationFrequency::Instant->value,
            NotificationEventType::PaymentDone->value => NotificationFrequency::Instant->value,
            NotificationEventType::PromiseCreated->value => NotificationFrequency::Instant->value,
            NotificationEventType::EstimateApproved->value => NotificationFrequency::Instant->value,
            NotificationEventType::PaymentPlanApproved->value => NotificationFrequency::Instant->value,
            NotificationEventType::AutoPayFailed->value => NotificationFrequency::Instant->value,
            NotificationEventType::SubscriptionCanceled->value => NotificationFrequency::Instant->value,
            NotificationEventType::SubscriptionExpired->value => NotificationFrequency::Instant->value,
        ];
        /** @var NotificationEventSetting[] $notifications */
        $notifications = NotificationEventSetting::where('member_id', $member->id)->execute();
        $notificationResults = [];
        foreach ($notifications as $item) {
            $notificationResults[$item->getNotificationType()->value] = $item->getFrequency()->value;
        }
        $this->assertEquals($expected, $notificationResults);
        $member->refresh();
        $this->assertEquals(1, $member->notifications);
    }

    public function testSwitch(): void
    {
        self::$company->features->disable('notifications_v2_default');
        $email = 'switchNotifications@test.com';
        $user = $this->createUser($email);
        $member = $this->createMember($user);
        $member->saveOrFail();
        NotificationEventSetting::where('member_id', $member->id)->delete();

        Notification::where('user_id', $user->id)->where('event', EventType::InvoiceViewed->value)->delete();
        Notification::where('user_id', $user->id)->where('event', EventType::InvoiceCommented->value)->delete();
        $notification = Notification::where('user_id', $user->id)->where('event', EventType::SubscriptionCanceled->value)->one();
        $notification->enabled = false;
        $notification->saveOrFail();

        $request = new Request();
        $request->attributes->set('model_id', $member->id);
        $request->request->set('notifications', 1);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $route = new MemberFrequencyUpdateApiRoute();
        $route->setModel($member);
        self::getService('test.api_runner')->run($route, $request);

        /** @var NotificationEventSetting[] $events */
        $events = NotificationEventSetting::where('member_id', $member->id)->execute();
        $this->assertCount(9, $events);
        $result = array_map(fn (NotificationEventSetting $item) => (int) $item->notification_type, $events);
        sort($result);
        $this->assertEquals([1, 2, 3, 5, 6, 7, 8, 9, 10], $result);
        $this->assertEquals(9, array_sum(array_map(fn (NotificationEventSetting $item) => $item->frequency, $events)));
    }

    public function testNewMember(): void
    {
        $event = $this->createUserEvent('testNewMember@determineUser.com');
        Member::determineUser($event);
        $this->assertTrue($event->getModel()->user()->isTemporary());

        $settings = new CompanySamlSettings();
        $settings->company = self::$company;
        $settings->domain = 'example.com';
        $settings->cert = 'test';
        $settings->entity_id = 1;
        $settings->sso_url = 'https://example.com';
        $settings->saveOrFail();

        $event = $this->createUserEvent('testNewMember2@determineUser.com');
        Member::determineUser($event);
        $this->assertTrue($event->getModel()->user()->isTemporary());

        $event = $this->createUserEvent('testNewMember3@determineUser.com');
        $settings->disable_non_sso = true;
        $settings->saveOrFail();

        Member::determineUser($event);
        $this->assertFalse($event->getModel()->user()->isTemporary());
    }

    private function createUserEvent(string $email): ModelCreated
    {
        User::where('email', $email)->delete();

        $member = new Member();
        $member->email = $email; /* @phpstan-ignore-line */
        $member->first_name = 'Test'; /* @phpstan-ignore-line */
        $member->last_name = 'Test'; /* @phpstan-ignore-line */
        $event = new ModelCreated($member);

        return $event;
    }
}
