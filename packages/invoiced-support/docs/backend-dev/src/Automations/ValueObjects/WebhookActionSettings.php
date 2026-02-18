<?php

namespace App\Automations\ValueObjects;

use App\Automations\Exception\AutomationException;
use App\Core\Utils\Enums\ObjectType;

class WebhookActionSettings extends AbstractActionSettings
{
    public function __construct(
        public string $url
    ) {
    }

    public function validate(ObjectType $sourceObject): void
    {
        if (!str_starts_with($this->url, 'http') || !filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new AutomationException('Invalid URL');
        }
    }
}
