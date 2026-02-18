<?php

namespace App\Automations\ValueObjects;

use App\Automations\Exception\AutomationException;
use App\Core\Utils\Enums\ObjectType;

class SendInternalNotificationActionSettings extends AbstractActionSettings
{
    /** @var int[] */
    public array $members;

    public function __construct(
        public string $message,
        array $members,
    ) {
        $this->members = array_map(fn ($member) => is_numeric($member) ? $member : $member->id, $members);
    }

    public function validate(ObjectType $sourceObject): void
    {
        foreach ($this->members as $member) {
            if (!is_numeric($member)) {
                throw new AutomationException('Invalid member: '.$member);
            }
        }
    }
}
