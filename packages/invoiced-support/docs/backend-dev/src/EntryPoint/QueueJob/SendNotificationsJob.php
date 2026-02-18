<?php

namespace App\EntryPoint\QueueJob;

use App\Companies\Models\Member;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Libs\NotificationEmailFactory;
use App\Notifications\Libs\NotificationEmailSender;
use App\Notifications\Models\AbstractNotificationEventSetting;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Notifications\Models\NotificationEventSetting;

/**
 * Sends user notifications in the new (v2) notification system.
 */
class SendNotificationsJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, StatsdAwareInterface, MaxConcurrencyInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private NotificationEmailFactory $emailFactory,
        private NotificationEmailSender $sender,
    ) {
    }

    public function perform(): void
    {
        $frequency = $this->args['frequency'] ?? null;
        if (!$frequency) {
            return;
        }
        $memberId = $this->args['member_id'];
        $member = Member::find($memberId);
        if (!$member) {
            return;
        }

        $query = $member->allowed('notifications.edit')
            ? NotificationEventSetting::where('member_id', $member->id)
            : NotificationEventCompanySetting::query();

        $frequency = NotificationFrequency::from($frequency)->toInteger();
        /** @var AbstractNotificationEventSetting[] $settings */
        $settings = $query->where('frequency', $frequency)->all();
        foreach ($settings as $setting) {
            $events = $this->emailFactory->getEvents($setting->notification_type, $member);
            if ($events) {
                $notificationEmail = $this->emailFactory->build($setting->notification_type);
                $this->sender->send($events, $member, $notificationEmail);
                $this->statsd->increment('user_notification.sent', 1.0, ['notification' => $setting->getNotificationType()->value]);
            }
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        // 20 jobs for all accounts.
        return 20;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'process_notification';
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
