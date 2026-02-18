<?php

namespace App\Network\Command;

use App\Network\Event\NetworkConnectionDeletedEvent;
use App\Network\Models\NetworkConnection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DeleteNetworkConnection
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function remove(NetworkConnection $connection): void
    {
        // emit an event for other listeners to add behavior
        $this->dispatcher->dispatch(new NetworkConnectionDeletedEvent($connection));

        // delete the invitation after listeners
        $connection->deleteOrFail();
    }
}
