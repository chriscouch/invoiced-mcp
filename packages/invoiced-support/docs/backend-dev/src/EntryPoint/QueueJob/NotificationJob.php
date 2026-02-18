<?php

namespace App\EntryPoint\QueueJob;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Queue\Queue;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\Metadata\Libs\RestrictionQueryBuilder;
use App\Notifications\Emitters\EmailEmitter;
use App\Notifications\Emitters\NullEmitter;
use App\Notifications\Emitters\SlackEmitter;
use App\Notifications\Interfaces\EmitterInterface;
use App\Notifications\Models\Notification;
use App\Notifications\ValueObjects\Evaluate;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Background job to send out notifications over
 * all supported emitters.
 */
class NotificationJob extends AbstractResqueJob implements StatsdAwareInterface, TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    use StatsdAwareTrait;

    private const USER_ID_CONSTRAINTS = [
        // these events will only trigger notifications
        // when the event was initiated by one of the specified users
        // i.e. the customer or Invoiced
        EventType::InvoicePaymentExpected->value => [
            -1, // not signed in
        ],
        EventType::InvoicePaid->value => [
            -1, // not signed in
            User::INVOICED_USER,
        ],
        EventType::SubscriptionCanceled->value => [
            -1, // not signed in
            User::INVOICED_USER,
        ],
        EventType::PaymentCreated->value => [
            -1, // not signed in
            User::INVOICED_USER,
        ],
    ];

    private static array $emitters = [
        Notification::EMITTER_EMAIL => EmailEmitter::class,
        Notification::EMITTER_SLACK => SlackEmitter::class,
        Notification::EMITTER_NULL => NullEmitter::class,
    ];

    public function __construct(private Queue $jobQueue, private ServiceLocator $emitterLocator, private EventStorageInterface $eventStorage)
    {
    }

    public function setJobQueue(Queue $jobQueue): void
    {
        $this->jobQueue = $jobQueue;
    }

    /**
     * Queues a notification for the given event.
     */
    public function queue(Notification $notification, Event $event): void
    {
        $payload = [
            'id' => $notification->id(),
            'eventId' => $event->id(),
            'userId' => $notification->user_id,
            'medium' => $notification->medium,
            'queued_at' => microtime(true),
            'tenant_id' => $notification->tenant_id,
        ];

        $this->statsd->increment('notification.queued.'.$notification->medium);

        $this->jobQueue->enqueue(self::class, $payload);
    }

    public function perform(): void
    {
        $event = Event::queryWithoutMultitenancyUnsafe()
            ->where('id', $this->args['eventId'])
            ->oneOrNull();
        if (!($event instanceof Event)) {
            return;
        }

        // hydrate the event data because it will be used by the notification
        $event->hydrateFromStorage($this->eventStorage);

        $medium = $this->args['medium'];
        $userId = $this->args['userId'];

        $user = $userId ? User::find($userId) : null;

        $this->send($event, $medium, $user);

        $start = $this->args['queued_at'];
        $time = round((microtime(true) - $start) * 1000);
        $this->statsd->timing('notification.delivery_time', $time);
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 10 notifications can be sent out per account a time.
        return 10;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'notification:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }

    /**
     * Checks if a notification should be sent for a given event.
     */
    public function canSend(Notification $notification, Event $event): bool
    {
        // check if the notification conditions are met
        $rule = $notification->toRule();
        $evaluator = new Evaluate($rule, $event);
        if (!$evaluator->evaluate()) {
            return false;
        }

        // check event user ID constraints
        // i.e. some events are only sent if the customer
        // initiated it
        if (0 === count($rule->getConditions())) {
            $constraints = array_value(self::USER_ID_CONSTRAINTS, $event->type);
            if (is_array($constraints) && !in_array($event->user_id, $constraints)) {
                return false;
            }
        }

        // if the notification rule does not involve a specific user
        // then we need to skip the user permission checks
        $user = $notification->user();
        if (!$user) {
            return true;
        }

        // cannot send notifications to temporary users
        if ($user->isTemporary()) {
            return false;
        }

        // check if still a member
        $member = Member::getForUser($user);
        if (!($member instanceof Member)) {
            // if the member has removed then we can delete the notification rule
            $notifications = Notification::where('user_id', $user->id())->all();
            foreach ($notifications as $notification) {
                $notification->delete();
            }

            return false;
        }

        if ($member->notifications) {
            return false;
        }

        // check if the member is allowed to receive notifications about this account
        if (Member::CUSTOM_FIELD_RESTRICTION == $member->restriction_mode) {
            if ($restrictions = $member->restrictions()) {
                $customer = array_value($event->getAssociations(), 'customer');
                $query = Customer::query()->where('id', $customer);
                $queryBuilder = new RestrictionQueryBuilder($notification->tenant(), $restrictions);
                $queryBuilder->addToOrmQuery('id', $query);
                $n = $query->count();
                if (0 === $n) {
                    return false;
                }
            }
        } elseif (Member::OWNER_RESTRICTION == $member->restriction_mode) {
            $customer = array_value($event->getAssociations(), 'customer');
            $query = Customer::query()->where('id', $customer)
                ->where('owner_id = '.$member->user_id);
            $n = $query->count();
            if (0 === $n) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sends a notification for the given event.
     *
     * @return bool true if the notification was emitted
     */
    public function send(Event $event, string $medium, User $user = null): bool
    {
        $sent = $this->getEmitter($medium)
            ->emit($event, $user);

        if (!$sent) {
            return false;
        }

        $this->statsd->increment('notification.sent.'.$medium);

        return true;
    }

    /**
     * Gets the emitter for this notification.
     */
    public function getEmitter(string $medium): EmitterInterface
    {
        return $this->emitterLocator->get(self::$emitters[$medium]);
    }
}
