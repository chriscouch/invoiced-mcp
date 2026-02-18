<?php

namespace App\Network\Event;

use App\Network\Models\NetworkDocument;
use App\Network\Models\NetworkDocumentStatusTransition;
use Symfony\Contracts\EventDispatcher\Event;

class DocumentTransitionEvent extends Event
{
    public function __construct(
        public readonly NetworkDocument $document,
        public readonly NetworkDocumentStatusTransition $statusHistory,
    ) {
    }
}
