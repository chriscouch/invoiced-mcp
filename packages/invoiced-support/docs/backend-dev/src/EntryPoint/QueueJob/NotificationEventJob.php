<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Database\DatabaseHelper;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationEventMemberFactory;
use App\Notifications\Models\NotificationEvent;
use Doctrine\DBAL\Connection;

class NotificationEventJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private Connection $database,
        private NotificationEventMemberFactory $factory
    ) {
    }

    public function perform(): void
    {
        $type = NotificationEventType::from($this->args['type']);
        $members = $this->factory->getMemberIds($type, $this->args['contextId']);
        if (!$members) {
            return;
        }

        $model = new NotificationEvent();
        $model->object_id = $this->args['objectId'];
        $model->setType($type);
        if (isset($this->args['message'])) {
            $model->message = $this->args['message'];
        }
        $this->statsd->increment('user_notification.created', 1, ['notification' => $model->getType()->value]);

        $this->database->transactional(function () use ($model, $members) {
            $model->saveOrFail();

            $data = [];
            foreach ($members as $member) {
                $data[] = $model->tenant_id;
                $data[] = $model->id;
                $data[] = $member;
            }
            DatabaseHelper::bulkInsert($this->database, 'NotificationRecipients', ['tenant_id', 'notification_event_id', 'member_id'], $data);
        });
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 20;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'notification_event';
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
