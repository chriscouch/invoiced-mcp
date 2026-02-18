<?php

namespace App\Network\Event;

use App\Network\Models\NetworkInvitation;
use Symfony\Contracts\EventDispatcher\Event;

class PostSendNetworkInvitationEvent extends Event
{
    public function __construct(
        public readonly NetworkInvitation $invitation,
    ) {
    }
}
