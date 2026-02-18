<?php

namespace App\Tests\Notifications;

use App\Companies\Models\Member;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Models\NotificationEventSetting;
use App\Tests\AppTestCase;
use App\Core\Orm\Exception\DriverException;

class NotificationEventSettingTest extends AppTestCase
{
    public function testCrud(): void
    {
        self::hasCompany();
        /** @var Member $member */
        $member = Member::query()->oneOrNull();

        NotificationEventSetting::query()->delete();

        $setting = new NotificationEventSetting();
        $setting->member = $member;
        $setting->setNotificationType(NotificationEventType::SubscriptionExpired);
        $setting->setFrequency(NotificationFrequency::Instant);
        $setting->saveOrFail();

        $this->assertEquals(1, NotificationEventSetting::query()->count());

        $setting = new NotificationEventSetting();
        $setting->member = $member;
        $setting->setNotificationType(NotificationEventType::SubscriptionExpired);
        $setting->setFrequency(NotificationFrequency::Daily);
        try {
            $setting->save();
            $this->assertFalse(true, 'no exception thrown');
        } catch (DriverException $e) {
        }

        $this->assertEquals(1, NotificationEventSetting::query()->count());

        $setting = new NotificationEventSetting();
        $setting->member = $member;
        $setting->setNotificationType(NotificationEventType::PaymentDone);
        $setting->setFrequency(NotificationFrequency::Daily);
        $setting->saveOrFail();

        $this->assertEquals(2, NotificationEventSetting::query()->count());

        $item = NotificationEventSetting::where([
            'member_id' => $member->id,
            'notification_type' => NotificationEventType::SubscriptionExpired->toInteger(),
        ])->one();
        $this->assertEquals(NotificationFrequency::Instant, $item->getFrequency());

        $item = NotificationEventSetting::where([
            'member_id' => $member->id,
            'notification_type' => NotificationEventType::PaymentDone->toInteger(),
        ])->one();
        $this->assertEquals(NotificationFrequency::Daily, $item->getFrequency());

        $item->delete();
        $this->assertEquals(1, NotificationEventSetting::query()->count());
    }
}
