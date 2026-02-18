<?php

namespace App\Integrations\Adyen\Libs;

use App\Integrations\Adyen\Enums\ChargebackEvent;
use App\Integrations\Adyen\Enums\PaymentEvent;
use App\Integrations\Adyen\Enums\RefundEvent;
use App\Integrations\Adyen\Enums\TokenizationEvent;
use App\Integrations\Adyen\Interfaces\AdyenWebhookInterface;

class PaymentWebhookFactory
{
    public function __construct(
        private readonly AdyenChargebackWebhook $adyenChargebackWebhook,
        private readonly AdyenPaymentWebhook $adyenPaymentWebhook,
        private readonly AdyenTokenizationWebhook $adyenTokenizationWebhook,
        private readonly AdyenRefundWebhook $adyenRefundWebhook,
        private readonly AdyenEmptyWebhook $adyenEmptyWebhook,
    ) {
    }

    public function get(array $item): AdyenWebhookInterface
    {
        if (ChargebackEvent::tryFrom($item['eventCode'])) {
            return $this->adyenChargebackWebhook;
        }
        if (PaymentEvent::tryFrom($item['eventCode'])) {
            return $this->adyenPaymentWebhook;
        }
        if (TokenizationEvent::tryFrom($item['eventCode'])) {
            return $this->adyenTokenizationWebhook;
        }
        if (RefundEvent::tryFrom($item['eventCode'])) {
            return $this->adyenRefundWebhook;
        }

        return $this->adyenEmptyWebhook;
    }
}
