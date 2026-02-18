<?php

namespace App\Webhooks\Interfaces;

use App\Webhooks\Models\WebhookAttempt;

interface PayloadStorageInterface
{
    /**
     * Saves the payload for a webhook attempt.
     */
    public function store(WebhookAttempt $attempt, string $content): void;

    /**
     * Retrieves the payload for a webhook attempt.
     */
    public function retrieve(WebhookAttempt $attempt): ?string;
}
