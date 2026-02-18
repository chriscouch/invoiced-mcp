<?php

namespace App\Tests\Notifications;

use App\Core\Authentication\Models\User;
use App\ActivityLog\Enums\EventType;
use App\Notifications\Models\Notification;
use App\Notifications\ValueObjects\Condition;
use App\Notifications\ValueObjects\Rule;
use App\Tests\AppTestCase;

class NotificationTest extends AppTestCase
{
    private static User $user;
    private static Notification $notification;

    public static function setUpBeforeClass(): void
    {
        self::$user = self::getService('test.user_context')->get();

        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::getService('test.database')->delete('Users', ['email' => 'notif.test@example.com']);
    }

    public function testToRuleNoConditions(): void
    {
        $notification = new Notification();

        $rule = $notification->toRule();
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals(Rule::MATCH_ANY, $rule->getMatch());
        $this->assertCount(0, $rule->getConditions());
    }

    public function testToRule(): void
    {
        $conditions = [
            new Condition('test', 'isSet'),
        ];

        $notification = new Notification();
        $notification->match_mode = Rule::MATCH_ALL;
        $notification->conditions = (string) json_encode($conditions);

        $rule = $notification->toRule();
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals(Rule::MATCH_ALL, $rule->getMatch());
        $this->assertCount(1, $rule->getConditions());
        $this->assertEquals((string) $conditions[0], (string) $rule->getConditions()[0]);
    }

    public function testToRuleManyConditions(): void
    {
        $conditions = [
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
            new Condition('test', 'isSet'),
        ];

        $notification = new Notification();
        $notification->conditions = (string) json_encode($conditions);

        $rule = $notification->toRule();
        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertCount(10, $rule->getConditions());
    }

    public function testCreate(): void
    {
        self::$notification = new Notification();
        $this->assertTrue(self::$notification->create([
            'event' => EventType::InvoiceViewed->value,
            'user_id' => self::$user->id(),
            'enabled' => true,
        ]));
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$notification->id(),
            'enabled' => true,
            'event' => EventType::InvoiceViewed->value,
            'user_id' => self::$user->id(),
            'match_mode' => Rule::MATCH_ANY,
            'medium' => Notification::EMITTER_EMAIL,
            'conditions' => '',
        ];

        $this->assertEquals($expected, self::$notification->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $notifications = Notification::all();

        $found = [];
        foreach ($notifications as $n) {
            $found[] = $n->id();
        }
        $this->assertTrue(in_array(self::$notification->id(), $found));
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$notification->delete());
    }
}
