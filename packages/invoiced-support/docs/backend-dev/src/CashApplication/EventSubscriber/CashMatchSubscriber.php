<?php

namespace App\CashApplication\EventSubscriber;

use App\CashApplication\Libs\CashApplicationMatchmaker;
use App\CashApplication\Models\Payment;
use App\ActivityLog\Models\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens for a payment created or updated event
 * and initiates a CashMatch job if the payment is
 * unapplied.
 */
class CashMatchSubscriber implements EventSubscriberInterface
{
    public function __construct(private CashApplicationMatchmaker $matchmaker)
    {
    }

    public function onEventDispatch(Event $event): void
    {
        if (!in_array($event->type, ['payment.created', 'payment.updated'])) {
            return;
        }

        $payment = Payment::queryWithTenant($event->tenant())
            ->where('id', $event->object_id)
            ->oneOrNull();
        if (!($payment instanceof Payment)) {
            return;
        }

        // Do not look for matches for payments created from bank feed transactions
        // because that is initiated elsewhere.
        if ('payment.created' === $event->type && $payment->bank_feed_transaction) {
            return;
        }

        if ($this->matchmaker->shouldLookForMatches($payment)) {
            $this->matchmaker->enqueue($payment, 'payment.updated' == $event->type);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'object_event.dispatch' => 'onEventDispatch',
        ];
    }
}
