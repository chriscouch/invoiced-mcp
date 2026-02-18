<?php

namespace App\Automations\ValueObjects;

class PostToSlackActionSettings extends AbstractActionSettings
{
    public function __construct(
        public string $channel,
        public string $message,
    ) {
    }
}
