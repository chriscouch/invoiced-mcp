<?php

namespace App\Sending\Email\Search;

use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\Sending\Email\Models\EmailParticipant;

class EmailParticipantSearchDocument implements SearchDocumentInterface
{
    public function __construct(private readonly EmailParticipant $emailParticipant)
    {
    }

    public function toSearchDocument(): array
    {
        return [
            'id' => $this->emailParticipant->id,
            'name' => $this->emailParticipant->name,
            'email_address' => (array) $this->emailParticipant->email_address,
        ];
    }
}
