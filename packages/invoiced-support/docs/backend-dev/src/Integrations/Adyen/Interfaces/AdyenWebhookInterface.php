<?php

namespace App\Integrations\Adyen\Interfaces;

interface AdyenWebhookInterface
{
    public function process(array $item): void;
}
