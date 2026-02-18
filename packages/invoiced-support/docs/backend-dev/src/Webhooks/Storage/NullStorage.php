<?php

namespace App\Webhooks\Storage;

use App\Webhooks\Interfaces\PayloadStorageInterface;
use App\Webhooks\Models\WebhookAttempt;

class NullStorage implements PayloadStorageInterface
{
    public function store(WebhookAttempt $attempt, string $content): void
    {
    }

    public function retrieve(WebhookAttempt $attempt): ?string
    {
        return $attempt->payload ?: null;
    }
}
