<?php

namespace App\Network\Event;

use App\Network\Models\NetworkDocument;
use Symfony\Contracts\EventDispatcher\Event;

class PostSendModelEvent extends Event
{
    public function __construct(
        public readonly object $model,
        public readonly NetworkDocument $document,
    ) {
    }
}
