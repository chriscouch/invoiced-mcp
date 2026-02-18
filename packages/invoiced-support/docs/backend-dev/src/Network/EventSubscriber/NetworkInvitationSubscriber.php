<?php

namespace App\Network\EventSubscriber;

use App\AccountsPayable\Models\Vendor;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\SendNetworkDocumentQueueJob;
use App\Network\Command\SendInvitationEmail;
use App\Network\Event\NetworkInvitationAcceptedEvent;
use App\Network\Event\NetworkInvitationDeclinedEvent;
use App\Network\Event\PostSendNetworkInvitationEvent;
use App\Network\Models\NetworkConnection;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NetworkInvitationSubscriber implements EventSubscriberInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    private bool $createdVendor = false;

    public function __construct(
        private SendInvitationEmail $inviteSender,
        private NotificationSpool $notificationSpool,
        private TenantContext $tenant,
        private Queue $queue,
    ) {
    }

    public function sentInvitation(PostSendNetworkInvitationEvent $event): void
    {
        $this->inviteSender->sendNetworkInvitation($event->invitation);
        $this->statsd->increment('network.invitation_sent');
    }

    public function acceptedInvitation(NetworkInvitationAcceptedEvent $event): void
    {
        $invitation = $event->invitation;
        $connection = $event->connection;

        // notify the invitation sender that it was accepted
        $this->tenant->runAs($invitation->from_company, function () use ($invitation, $connection) {
            if ($member = $invitation->sent_by_user) {
                $this->notificationSpool->spool(NotificationEventType::NetworkInvitationAccepted, $invitation->from_company_id, $connection->id, $member->id);
            }
        });

        // assign the connection to a vendor record on the customer's account
        $this->createdVendor = false;
        $this->tenant->runAs($connection->customer, function () use ($invitation, $connection) {
            if ($vendor = $invitation->vendor) {
                // assign the connection to a vendor, if known
                $vendor->network_connection = $connection;
                $vendor->saveOrFail();
            } else {
                // create as a new vendor
                $this->createVendor($connection->vendor, $connection);
            }
        });

        // assign the connection to a customer record on the vendor's account
        // (this should happen after the vendor connection assignment)
        $this->tenant->runAs($connection->vendor, function () use ($invitation, $connection) {
            if ($customer = $invitation->customer) {
                // assign the connection to a customer, if known
                $customer->network_connection = $connection;
                $customer->skipReconciliation();
                $customer->saveOrFail();

                // import all documents when a new vendor was created
                if ($this->createdVendor) {
                    $this->importAllDocuments($customer);
                }
            } else {
                // create as a new customer
                $this->createCustomer($connection->customer, $connection);
            }
        });

        $this->statsd->increment('network.invitation_accepted');
    }

    public function declinedInvitation(NetworkInvitationDeclinedEvent $event): void
    {
        // send a notification to the sender
        // will need to change tenant context
        $invitation = $event->invitation;
        if ($member = $invitation->sent_by_user) {
            $this->tenant->runAs($invitation->from_company, function () use ($invitation, $member) {
                $this->notificationSpool->spool(NotificationEventType::NetworkInvitationDeclined, $invitation->from_company_id, $invitation->id, $member->id);
            });
        }

        $this->statsd->increment('network.invitation_declined');
    }

    /**
     * Imports the customer's history into their new tenant.
     */
    private function importAllDocuments(Customer $customer): void
    {
        $this->queue->enqueue(SendNetworkDocumentQueueJob::class, [
            'tenant_id' => $customer->tenant_id,
            'customer' => $customer->id,
        ], QueueServiceLevel::Batch);
    }

    private function createCustomer(Company $customerCompany, NetworkConnection $connection): Customer
    {
        $customerName = $customerCompany->name ?: $customerCompany->username;

        $customer = new Customer();
        $customer->name = $customerName;
        $customer->address1 = $customerCompany->address1;
        $customer->address2 = $customerCompany->address2;
        $customer->city = $customerCompany->city;
        $customer->state = $customerCompany->state;
        $customer->postal_code = $customerCompany->postal_code;
        $customer->country = (string) $customerCompany->country;
        $customer->email = $customerCompany->email;
        $customer->network_connection = $connection;
        $customer->saveOrFail();

        return $customer;
    }

    private function createVendor(Company $vendorCompany, NetworkConnection $connection): Vendor
    {
        $vendorName = $vendorCompany->name ?: $vendorCompany->username;

        // Check for an existing vendor and connect that if there is a match.
        // An existing vendor must not have a network connection and name
        // must be an exact match.
        $vendor = Vendor::where('network_connection_id', null)
            ->where('name', $vendorName)
            ->oneOrNull();

        if (!$vendor) {
            $vendor = new Vendor();
            $this->createdVendor = true;
        }

        $vendor->active = true;
        $vendor->name = $vendorName;
        $vendor->network_connection = $connection;
        $vendor->saveOrFail();

        return $vendor;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostSendNetworkInvitationEvent::class => 'sentInvitation',
            NetworkInvitationAcceptedEvent::class => 'acceptedInvitation',
            NetworkInvitationDeclinedEvent::class => 'declinedInvitation',
        ];
    }
}
