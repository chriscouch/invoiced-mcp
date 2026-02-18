<?php

namespace App\Network\EventSubscriber;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\DiffCalculator;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Network\Enums\DocumentStatus;
use App\Network\Event\DocumentTransitionEvent;
use App\Network\Event\PostSendDocumentEvent;
use App\Network\Event\PostSendModelEvent;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Libs\DocumentEmailWriter;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Model;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NetworkDocumentSubscriber implements EventSubscriberInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private NotificationSpool $notificationSpool,
        private TenantContext $tenant,
        private EventSpool $eventSpool,
        private Connection $database,
    ) {
    }

    public function sentDocument(PostSendDocumentEvent $event): void
    {
        $document = $event->document;

        // create an event in the sender's activity log
        if ($event->previous) {
            // compute the diff from previous values
            $previous = $event->previous->getEventObject();
            $updated = $document->getEventObject();
            $diff = (array) (new DiffCalculator())->diff($previous, $updated);
        } else {
            $diff = [];
        }
        $this->eventSpool->enqueue(new PendingEvent(
            object: $document,
            type: EventType::NetworkDocumentSent,
            previous: $diff,
            parameters: ['tenant_id' => $document->from_company_id],
            // TODO: add associations from source model, customer, invoice, etc
        ));

        $this->tenant->runAs($document->to_company, function () use ($document, $diff) {
            // notify the recipient about the new document
            $this->notificationSpool->spool(NotificationEventType::NetworkDocumentReceived, $document->to_company_id, $document->id);

            // create an event in the recipient's activity log
            $this->eventSpool->enqueue(new PendingEvent(
                object: $document,
                type: EventType::NetworkDocumentReceived,
                associations: [['vendor', $this->getVendorId($document)]],
                // TODO: add associations to bill, vendor credit, etc
                previous: $diff,
                parameters: ['tenant_id' => $document->to_company_id],
            ));
        });

        $this->statsd->increment('network.document_sent');
    }

    public function markDocumentSent(PostSendModelEvent $event): void
    {
        $model = $event->model;
        if ($model instanceof SendableDocumentInterface) {
            if ($model instanceof Model && isset($model->network_document)) {
                $model->network_document = $event->document;
                $model->save();
            }
            DocumentEmailWriter::markDocumentSent($model, $this->database);
        }
    }

    public function documentTransition(DocumentTransitionEvent $event): void
    {
        $transition = $event->statusHistory;

        // Do not notify about these states because they are covered elsewhere
        if (in_array($transition->status, [DocumentStatus::PendingApproval])) {
            return;
        }

        $document = $event->document;
        // Figure out which company changed the status
        $changeAgent = $transition->company;
        $contextId = null;
        $counterparty = $document->to_company;
        if ($document->to_company_id == $changeAgent->id) {
            $counterparty = $document->from_company;
            if ($connection = NetworkConnection::forCustomer($document->from_company, $document->to_company)) {
                $contextId = Customer::queryWithTenant($document->from_company)->
                    where('network_connection_id', $connection->id)->
                    oneOrNull()?->id;
            }
        }
        $this->tenant->runAs($counterparty, function () use ($counterparty, $transition, $contextId) {
            // notify the counterparty about the status change
            // TODO: need to find way to include customer context in this notification
            $this->notificationSpool->spool(NotificationEventType::NetworkDocumentStatusChange, $counterparty->id, $transition->id, $contextId);
        });

        $this->tenant->runAs($document->from_company, function () use ($document) {
            // create an event in the sender's activity log
            $this->eventSpool->enqueue(new PendingEvent(
                object: $document,
                type: EventType::NetworkDocumentStatusUpdated,
                parameters: ['tenant_id' => $document->from_company_id],
                // TODO: add associations from source model, customer, invoice, etc
            ));
        });

        $this->tenant->runAs($document->to_company, function () use ($document) {
            // create an event in the recipient's activity log
            $this->eventSpool->enqueue(new PendingEvent(
                object: $document,
                type: EventType::NetworkDocumentStatusUpdated,
                associations: [['vendor', $this->getVendorId($document)]],
                // TODO: add associations from bill, vendor credit, etc
                parameters: ['tenant_id' => $document->to_company_id],
            ));
        });

        $this->statsd->increment('network.document_status_transition', 1.0, ['status' => $transition->status->name]);
    }

    private function getVendorId(NetworkDocument $networkDocument): int
    {
        return (int) $this->database->fetchOne('SELECT v.id FROM Vendors v JOIN NetworkConnections c ON v.network_connection_id=c.id WHERE v.tenant_id=:tenantId AND c.vendor_id=:vendorTenantId', [
            'tenantId' => $networkDocument->to_company_id,
            'vendorTenantId' => $networkDocument->from_company_id,
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostSendDocumentEvent::class => 'sentDocument',
            PostSendModelEvent::class => 'markDocumentSent',
            DocumentTransitionEvent::class => 'documentTransition',
        ];
    }
}
