<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\DocumentView;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;

/**
 * Records views for a document.
 */
class DocumentViewTracker implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private EventSpool $eventSpool,
        private NotificationSpool $notificationSpool,
        private CustomerPortalEvents $events,
    ) {
    }

    public function addView(ReceivableDocument $document, ?string $agent, ?string $ip): ?DocumentView
    {
        if (!$agent || !$ip) {
            return null;
        }

        // check if a view already exists (dedupe)
        $view = DocumentView::where('document_type', $document->object)
            ->where('document_id', $document)
            ->where('user_agent', $agent)
            ->where('ip', $ip)
            ->oneOrNull();

        if ($view instanceof DocumentView) {
            return $view;
        }

        // create a view
        $view = new DocumentView();
        $view->document_type = $document->object;
        $view->document_id = (int) $document->id();
        $view->user_agent = $agent;
        $view->ip = $ip;
        if (!$view->save()) {
            return null;
        }

        // record the event
        if ($document instanceof Estimate) {
            $eventName = EventType::EstimateViewed;
        } elseif ($document instanceof CreditNote) {
            $eventName = EventType::CreditNoteViewed;
        } else {
            $eventName = EventType::InvoiceViewed;
        }
        $associations = $document->getEventAssociations(); // inherit associations from document
        $pendingEvent = new PendingEvent(
            object: $view,
            type: $eventName,
            associations: $associations
        );
        $this->eventSpool->enqueue($pendingEvent);

        // mark document as viewed
        if (!$document->viewed) {
            $document->viewed = true;
            $document->skipReconciliation();
            $document->skipClosedCheck()->save();
        }

        if ($document instanceof Estimate) {
            $this->notificationSpool->spool(NotificationEventType::EstimateViewed, $document->tenant_id, $document->id, $document->customer);
        } elseif ($document instanceof Invoice) {
            $this->notificationSpool->spool(NotificationEventType::InvoiceViewed, $document->tenant_id, $document->id, $document->customer);
        }

        // record it on statsd
        $this->statsd->increment($document->object.'.viewed');

        // record the customer portal event
        if ($document instanceof Invoice) {
            $this->events->track($document->customer(), CustomerPortalEvent::ViewInvoice);
        } elseif ($document instanceof Estimate) {
            $this->events->track($document->customer(), CustomerPortalEvent::ViewEstimate);
        } elseif ($document instanceof CreditNote) {
            $this->events->track($document->customer(), CustomerPortalEvent::ViewCreditNote);
        }

        return $view;
    }
}
