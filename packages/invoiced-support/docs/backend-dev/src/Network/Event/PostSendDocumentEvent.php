<?php

namespace App\Network\Event;

use App\Network\Models\NetworkDocument;
use Symfony\Contracts\EventDispatcher\Event;

class PostSendDocumentEvent extends Event
{
    public function __construct(
        public readonly NetworkDocument $document,
        public readonly ?NetworkDocument $previous,
    ) {
    }
}
