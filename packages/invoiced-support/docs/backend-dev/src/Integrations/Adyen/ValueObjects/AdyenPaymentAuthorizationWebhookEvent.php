<?php

namespace App\Integrations\Adyen\ValueObjects;

use Symfony\Contracts\EventDispatcher\Event;

class AdyenPaymentAuthorizationWebhookEvent extends Event
{
    public function __construct(
        public readonly array $data,
    ) {
    }
}
