<?php

namespace App\Network\Command;

use App\Network\Event\NetworkInvitationDeclinedEvent;
use App\Network\Exception\NetworkInviteException;
use App\Network\Models\NetworkInvitation;
use Carbon\CarbonImmutable;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DeclineNetworkInvitation
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * @throws NetworkInviteException
     */
    public function decline(NetworkInvitation $invitation): void
    {
        if ($invitation->declined) {
            return;
        }

        $invitation->declined = true;
        $invitation->declined_at = CarbonImmutable::now();
        $invitation->saveOrFail();

        // emit an event for other listeners to add behavior
        $this->dispatcher->dispatch(new NetworkInvitationDeclinedEvent($invitation));
    }
}
