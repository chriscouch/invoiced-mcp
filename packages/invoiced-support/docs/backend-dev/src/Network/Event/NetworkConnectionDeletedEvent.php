<?php

namespace App\Network\Event;

use App\Network\Models\NetworkConnection;

class NetworkConnectionDeletedEvent
{
    public function __construct(public readonly NetworkConnection $connection)
    {
    }
}
