<?php

namespace App\Notifications\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\PaymentLinkSession;
use App\CashApplication\Models\Payment;
use App\Chasing\Models\PromiseToPay;
use App\Chasing\Models\Task;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Network\Enums\DocumentStatus;
use App\Network\Enums\NetworkDocumentType;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentStatusTransition;
use App\Network\Models\NetworkInvitation;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Models\Charge;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use App\SubscriptionBilling\Models\Subscription;
use Carbon\Carbon;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use App\PaymentProcessing\Models\Refund;

/**
 * Transforms notification events from the database to
 * a format that the frontend/API can use to display
 * a list of notifications that have happened.
 */
class NotificationEventSerializer
{
    private array $objects = [
        InboxEmail::class => [
            'select' => 'v.id, v.subject, v.thread_id, t.customer_id, t.inbox_id, t.related_to_type, t.related_to_id',
            'join' => [
                ['v', 'EmailThreads', 't', 't.id = v.thread_id'],
            ],
        ],
        EmailThread::class => [
            'select' => 'v.id, v.name, v.customer_id, v.inbox_id',
        ],
        Task::class => [
            'select' => 'v.id, v.name, v.customer_id, v.bill_id, v.vendor_credit_id, v.action',
        ],
        Invoice::class => [
            'select' => 'v.id, v.number',
        ],
        Estimate::class => [
            'select' => 'v.id, v.number',
        ],
        Payment::class => [
            'select' => 'v.id, c.id as customer_id, c.name as customer_name, v.amount, v.currency',
            'join' => [
                ['v', 'Customers', 'c', 'c.id = v.customer'],
             ],
        ],
        PromiseToPay::class => [
            'select' => 'v.id, c.id as customer_id, c.name as customer_name, v.amount, v.currency, i.number, v.invoice_id',
            'join' => [
                ['v', 'Customers', 'c', 'c.id = v.customer_id'],
                ['v', 'Invoices', 'i', 'i.id = v.invoice_id'],
            ],
        ],
        PaymentPlan::class => [
            'select' => 'v.id, i.number,  v.invoice_id',
            'join' => [
                ['v', 'Invoices', 'i', 'i.id = v.invoice_id'],
            ],
        ],
        Charge::class => [
            'select' => 'v.id, c.id as customer_id, c.name as customer_name, v.amount, v.currency, v.failure_message, v.payment_id',
            'join' => [
                ['v', 'Customers', 'c', 'c.id = v.customer_id'],
            ],
        ],
        Subscription::class => [
            'select' => 'v.id, c.id as customer_id, c.name as customer_name, p.name as plan',
            'join' => [
                ['v', 'Customers', 'c', 'c.id = v.customer'],
                ['v', 'Plans', 'p', 'p.internal_id = v.plan_id'],
            ],
        ],
        ReconciliationError::class => [
            'select' => 'v.id, v.message',
        ],
        Customer::class => [
            'select' => 'v.id, v.id as customer_id, v.name as customer_name, p.name as sign_up_page_name',
            'join' => [
                ['v', 'SignUpPages', 'p', 'p.id = v.sign_up_page_id'],
            ],
        ],
        NetworkInvitation::class => [
            'select' => 'v.id, v.email, c.name',
            'join' => [
                ['v', 'Companies', 'c', 'c.id = v.to_company_id'],
            ],
        ],
        NetworkConnection::class => [
            'select' => 'v.id, v.customer_id, v.vendor_id, customer.name as customer_name, vendor.name as vendor_name',
            'join' => [
                ['v', 'Companies', 'customer', 'customer.id = v.customer_id'],
                ['v', 'Companies', 'vendor', 'vendor.id = v.vendor_id'],
            ],
        ],
        NetworkDocument::class => [
            'select' => 'v.id, v.type as document_type, v.reference, from_company.name as from_name',
            'join' => [
                ['v', 'Companies', 'from_company', 'from_company.id = v.from_company_id'],
            ],
        ],
        NetworkDocumentStatusTransition::class => [
            'select' => 'v.id, v.status as document_status, v.document_id, document.type as document_type, document.reference, v.description, company.name as from_name',
            'join' => [
                ['v', 'Companies', 'company', 'company.id = v.company_id'],
                ['v', 'NetworkDocuments', 'document', 'document.id = v.document_id'],
            ],
        ],
        PaymentLinkSession::class => [
            'select' => 'v.id, l.name, c.id as customer_id, c.name as customer_name',
            'join' => [
                ['v', 'PaymentLinks', 'l', 'l.id = v.payment_link_id'],
                ['v', 'Customers', 'c', 'c.id = v.customer_id'],
            ],
        ],
        Refund::class => [
            'select' => 'v.id, v.amount, v.tenant_id, v.currency, v.status, v.failure_message, c.id as charge_id, c.gateway_id as charge_gateway_id, cust.id as customer_id, cust.name as customer_name, p.id as payment_id',
            'join' => [
                ['v', 'Charges', 'c', 'c.id = v.charge_id'],
                ['v', 'Payments', 'p', 'p.id = c.payment_id'],
                ['c', 'Customers', 'cust', 'cust.id = c.customer_id'],
            ],
        ],
    ];

    private array $items = [];

    public function __construct(private Connection $connection)
    {
        // set defaults for query builder
        foreach ($this->objects as $key => $object) {
            $this->objects[$key] = array_replace([
                'ids' => [],
                'table' => (new $key())->getTablename(), /* @phpstan-ignore-line */
                'select' => 'v.id, v.name',
                'join' => null,
            ], $object);
        }
    }

    /**
     * Adds an event to later be transformed.
     */
    public function add(NotificationEvent $event): void
    {
        $type = $event->getType();
        if (!isset($this->items[$type->value])) {
            $this->items[$type->value] = [];
        }
        // special case for automation triggers
        $key = NotificationEventType::AutomationTriggered === $type ? $event->message : $event->object_id;
        $this->items[$type->value][$key] = [
            'created_at' => Carbon::createFromTimestamp($event->created_at)->toIso8601String(),
            'object_id' => $event->object_id,
            'type' => $type->value,
            'message' => $event->message,
        ];

        $objectType = $this->getObjectType($type);
        $this->objects[$objectType]['ids'][] = $event->object_id;
    }

    /**
     * Generates the transformed events that have been added.
     */
    public function serialize(): array
    {
        $result = isset($this->items[NotificationEventType::AutomationTriggered->value]) ? array_values($this->items[NotificationEventType::AutomationTriggered->value]) : [];
        foreach ($this->objects as $objectType => $parameters) {
            // special case for automation triggers
            if (NotificationEvent::class === $objectType) {
                continue;
            }
            if (!$parameters['ids']) {
                continue;
            }
            $ids = array_unique($parameters['ids']);
            $qb = $this->connection->createQueryBuilder();
            $qb->select($parameters['select'])
                ->from($parameters['table'], 'v');
            if ($parameters['join']) {
                foreach ($parameters['join'] as $join) {
                    $qb->leftJoin(...$join);
                }
            }
            $itemData = $qb->where($qb->expr()->in('v.id', ':ids'))
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->fetchAllAssociative();

            $collectionTypes = $this->applyToObjectTypes($objectType);
            foreach ($itemData as $item) {
                $id = $item['id'];

                // convert document type enum to string value
                if (isset($item['document_type'])) {
                    $item['document_type'] = NetworkDocumentType::from($item['document_type'])->name;
                }

                // convert status enum to string value
                if (isset($item['document_status'])) {
                    $item['document_status'] = DocumentStatus::from($item['document_status'])->name;
                }

                // convert related to type enum to string value
                if (isset($item['related_to_type'])) {
                    $item['related_to_type'] = ObjectType::from($item['related_to_type'])->typeName();
                }

                foreach ($collectionTypes as $collectionType) {
                    if (isset($this->items[$collectionType->value][$id])) {
                        $resultItem = $this->items[$collectionType->value][$id];
                        $resultItem['metadata'] = $item;
                        $result[] = $resultItem;
                    }
                }
            }
        }

        usort($result, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $result;
    }

    private function getObjectType(NotificationEventType $type): string
    {
        return match ($type) {
            NotificationEventType::AutoPayFailed, NotificationEventType::AutoPaySucceeded => Charge::class,
            NotificationEventType::AutomationTriggered => NotificationEvent::class,
            NotificationEventType::EmailReceived => InboxEmail::class,
            NotificationEventType::EstimateApproved, NotificationEventType::EstimateViewed => Estimate::class,
            NotificationEventType::InvoiceViewed => Invoice::class,
            NotificationEventType::LockboxCheckReceived, NotificationEventType::PaymentDone => Payment::class,
            NotificationEventType::NetworkDocumentReceived => NetworkDocument::class,
            NotificationEventType::NetworkDocumentStatusChange => NetworkDocumentStatusTransition::class,
            NotificationEventType::NetworkInvitationAccepted => NetworkConnection::class,
            NotificationEventType::NetworkInvitationDeclined => NetworkInvitation::class,
            NotificationEventType::PaymentLinkCompleted => PaymentLinkSession::class,
            NotificationEventType::PaymentPlanApproved => PaymentPlan::class,
            NotificationEventType::PromiseCreated => PromiseToPay::class,
            NotificationEventType::ReconciliationError => ReconciliationError::class,
            NotificationEventType::SignUpPageCompleted, NotificationEventType::DisabledMethodsOnSignUpPageCompleted => Customer::class,
            NotificationEventType::SubscriptionCanceled, NotificationEventType::SubscriptionExpired => Subscription::class,
            NotificationEventType::TaskAssigned => Task::class,
            NotificationEventType::ThreadAssigned => EmailThread::class,
            NotificationEventType::RefundReversalApplied => Refund::class,
        };
    }

    /**
     * Finds a list of all notification events possible for a given object type.
     *
     * @return NotificationEventType[]
     */
    private function applyToObjectTypes(string $objectType): array
    {
        $result = [];
        foreach (NotificationEventType::cases() as $type) {
            if ($this->getObjectType($type) == $objectType) {
                $result[] = $type;
            }
        }

        return $result;
    }
}
