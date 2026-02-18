<?php

namespace App\Network\EventSubscriber;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Network\Command\TransitionDocumentStatus;
use App\Network\Enums\DocumentStatus;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Models\NetworkDocument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to key model events in order to
 * perform necessary updates through the network.
 */
class NetworkModelSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TransitionDocumentStatus $transitionDocumentStatus,
    ) {
    }

    public function onEventDispatch(Event $event): void
    {
        if (EventType::InvoicePaid->value == $event->type) {
            $this->markInvoicePaid($event);
        }
    }

    /**
     * Transitions the status on an invoice sent through the network
     * when the invoice is marked as paid.
     * WARNING: This only works with payment techniques that produce events.
     * For example, creating a payment through a spreadsheet upload would
     * not invoke this code path.
     */
    private function markInvoicePaid(Event $event): void
    {
        $object = $event->object;
        $invoiceNumber = $object?->number ?? null;
        $networkConnection = $object?->customer['network_connection_id'] ?? null;
        if (!$invoiceNumber || !$networkConnection) {
            return;
        }

        $document = NetworkDocument::where('from_company_id', $event->tenant())
            ->where('type', NetworkDocumentType::Invoice->value)
            ->where('reference', $invoiceNumber)
            ->oneOrNull();
        if (!$document instanceof NetworkDocument) {
            return;
        }

        if ($this->transitionDocumentStatus->isTransitionAllowed($document->current_status, DocumentStatus::Paid, true)) {
            $this->transitionDocumentStatus->performTransition($document, $event->tenant(), DocumentStatus::Paid, null, flush: true);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'object_event.dispatch' => 'onEventDispatch',
        ];
    }
}
