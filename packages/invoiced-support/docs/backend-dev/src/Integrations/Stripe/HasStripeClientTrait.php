<?php

namespace App\Integrations\Stripe;

use Stripe\StripeClient;

trait HasStripeClientTrait
{
    private StripeClient $stripe;
    private string $stripeSecret;

    private function getStripe(): StripeClient
    {
        if (!isset($this->stripe)) {
            $this->stripe = new StripeClient([
                'api_key' => $this->stripeSecret,
                'stripe_version' => '2020-08-27',
            ]);
        }

        return $this->stripe;
    }

    /**
     * Used for testing.
     */
    public function setStripe(StripeClient $stripe): void
    {
        $this->stripe = $stripe;
    }
}
