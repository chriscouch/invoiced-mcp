<?php

namespace App\Sending\Email\Models;

use App\Sending\Email\ValueObjects\NamedAddress;
use App\Core\Orm\Model;

/**
 * Class InboxDecorator
 * The decorator class should extend model,
 * to return proper result.
 */
class InboxDecorator extends Model
{
    public function __construct(
        private Inbox $inbox,
        private string $emailDomain,
    ) {
    }

    public function toArray(): array
    {
        $data = $this->inbox->toArray();
        $data['email'] = $this->getEmailAddress();

        return $data;
    }

    /**
     * Gets the email address for this inbox.
     */
    public function getEmailAddress(): string
    {
        return "{$this->inbox->external_id}@{$this->emailDomain}";
    }

    public function getNamedEmailAddress(): NamedAddress
    {
        return new NamedAddress($this->getEmailAddress(), $this->inbox->tenant()->name);
    }
}
