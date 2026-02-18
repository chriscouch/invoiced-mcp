<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\CompanyCard;
use App\Companies\Models\Company;
use App\Integrations\Stripe\HasStripeClientTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\Card;
use Stripe\Exception\ExceptionInterface;
use Stripe\PaymentMethod;

class CreateCompanyCard implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use HasStripeClientTrait;

    public function __construct(
        string $stripePlatformSecret,
        private string $environment,
        private string $inboundEmailDomain,
    ) {
        $this->stripeSecret = $stripePlatformSecret;
    }

    /**
     * Creates a Stripe setup intent for adding a company card.
     *
     * @throws AccountsPayablePaymentException
     */
    public function start(Company $company): array
    {
        try {
            $stripe = $this->getStripe();

            // Build an email alias for use on Stripe
            $email = 'stripe-'.$company->id.'@'.$this->inboundEmailDomain;

            // Check for an existing customer based on a previously stored card
            $card = CompanyCard::withoutDeleted()
                ->where('gateway', 'stripe')
                ->oneOrNull();
            $customerId = $card?->stripe_customer;

            // Check for an existing customer based on the email address
            if (!$customerId) {
                $customers = $stripe->customers->all(['email' => $email]);
                if (count($customers->data) > 0) {
                    $customerId = $customers->data[0]->id;
                }
            }

            $customerParams = [
                'name' => $company->name,
                'email' => $email,
                'address' => [
                    'line1' => $company->address1,
                    'line2' => $company->address2,
                    'city' => $company->city,
                    'state' => $company->state,
                    'postal_code' => $company->postal_code,
                    'country' => $company->country,
                ],
                'metadata' => [
                    'platform' => '1',
                    'tenant_id' => $company->id,
                    'environment' => $this->environment,
                ],
            ];

            if ($customerId) {
                // Update the existing customer with the current values
                $stripe->customers->update($customerId, $customerParams);
            } else {
                // Create a Stripe customer if needed
                $customer = $stripe->customers->create($customerParams);
                $customerId = $customer->id;
            }

            // Create the card as a payment method on Stripe
            $intent = $stripe->setupIntents->create([
                'customer' => $customerId,
            ]);

            return [
                'client_secret' => $intent->client_secret,
            ];
        } catch (ExceptionInterface $e) {
            // log the error and throw a generic message
            $this->logger->error('Could not start company card workflow on Stripe', ['exception' => $e]);

            throw new AccountsPayablePaymentException('An unknown error has occurred.');
        }
    }

    /**
     * Finishes adding a company card given a completed Stripe setup intent ID.
     *
     * @throws AccountsPayablePaymentException
     */
    public function finish(string $setupIntentId): CompanyCard
    {
        try {
            // Retrieve the setup intent
            $stripe = $this->getStripe();
            $intent = $stripe->setupIntents->retrieve($setupIntentId, ['expand' => ['payment_method']]);

            // Save the model
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $intent->payment_method;

            $card = CompanyCard::where('stripe_payment_method', $paymentMethod->id)->oneOrNull();
            if ($card) {
                return $card;
            }

            /** @var Card $stripeCard */
            $stripeCard = $paymentMethod->card;
            $card = new CompanyCard();
            $card->gateway = 'stripe';
            $card->stripe_customer = (string) $intent->customer;
            $card->stripe_payment_method = $paymentMethod->id;
            $card->brand = $stripeCard->brand;
            $card->last4 = $stripeCard->last4;
            $card->exp_month = $stripeCard->exp_month;
            $card->exp_year = $stripeCard->exp_year;
            $card->funding = $stripeCard->funding;
            $card->issuing_country = $stripeCard->country;
            $card->saveOrFail();

            return $card;
        } catch (ExceptionInterface $e) {
            // log the error and throw a generic message
            $this->logger->error('Could not complete company card workflow on Stripe', ['exception' => $e]);

            throw new AccountsPayablePaymentException('An unknown error has occurred.');
        }
    }
}
