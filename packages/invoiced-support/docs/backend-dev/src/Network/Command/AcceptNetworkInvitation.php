<?php

namespace App\Network\Command;

use App\Companies\Models\Company;
use App\Network\Event\NetworkInvitationAcceptedEvent;
use App\Network\Exception\NetworkInviteException;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkInvitation;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AcceptNetworkInvitation
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * @throws NetworkInviteException
     */
    public function accept(NetworkInvitation $invitation): void
    {
        $toCompany = $invitation->to_company;
        if (!$toCompany instanceof Company) {
            throw new NetworkInviteException('There must be a company on the invitation');
        }

        // create the connection
        $connection = $this->newConnection($invitation);

        // emit an event for other listeners to add behavior
        $invitation->declined = false;
        $this->dispatcher->dispatch(new NetworkInvitationAcceptedEvent($invitation, $connection));

        // delete the invitation after listeners
        $invitation->deleteOrFail();
    }

    /**
     * @throws NetworkInviteException
     */
    private function newConnection(NetworkInvitation $invitation): NetworkConnection
    {
        $fromCompany = $invitation->from_company;
        /** @var Company $toCompany */
        $toCompany = $invitation->to_company;

        $connection = new NetworkConnection();
        if ($invitation->is_customer) {
            $connection->vendor = $fromCompany;
            $connection->customer = $toCompany;
        } else {
            $connection->vendor = $toCompany;
            $connection->customer = $fromCompany;
        }

        // check for ane existing connection before saving
        $existing = NetworkConnection::where('vendor_id', $connection->vendor)
            ->where('customer_id', $connection->customer)
            ->oneOrNull();
        if ($existing) {
            return $existing;
        }

        // create the connection if one doesn't exist
        $connection->saveOrFail();

        return $connection;
    }
}
