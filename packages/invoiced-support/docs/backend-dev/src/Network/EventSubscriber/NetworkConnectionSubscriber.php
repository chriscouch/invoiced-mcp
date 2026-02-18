<?php

namespace App\Network\EventSubscriber;

use App\AccountsPayable\Models\Vendor;
use App\Core\Multitenant\TenantContext;
use App\Network\Event\NetworkConnectionDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NetworkConnectionSubscriber implements EventSubscriberInterface
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function deletedConnection(NetworkConnectionDeletedEvent $event): void
    {
        // Mark any vendors from this network connection as inactive
        $connection = $event->connection;
        $this->tenant->runAs($connection->customer, function () use ($connection) {
            $vendors = Vendor::where('network_connection_id', $connection)
                ->where('active', true)
                ->all();
            foreach ($vendors as $vendor) {
                $vendor->active = false;
                $vendor->save();
            }
        });
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NetworkConnectionDeletedEvent::class => 'deletedConnection',
        ];
    }
}
