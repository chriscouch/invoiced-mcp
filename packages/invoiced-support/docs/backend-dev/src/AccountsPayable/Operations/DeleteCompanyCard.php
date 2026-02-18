<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\CompanyCard;
use App\Integrations\Stripe\HasStripeClientTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\Exception\ExceptionInterface;

class DeleteCompanyCard implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use HasStripeClientTrait;

    public function __construct(string $stripePlatformSecret)
    {
        $this->stripeSecret = $stripePlatformSecret;
    }

    public function delete(CompanyCard $card): void
    {
        $card->deleteOrFail();
        if ('stripe' != $card->gateway) {
            return;
        }

        try {
            // Delete the payment method on Stripe
            $stripe = $this->getStripe();
            $stripe->paymentMethods->detach((string) $card->stripe_payment_method, []);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not delete company card on Stripe', ['exception' => $e]);
            // do not report the exception to the user
        }
    }
}
