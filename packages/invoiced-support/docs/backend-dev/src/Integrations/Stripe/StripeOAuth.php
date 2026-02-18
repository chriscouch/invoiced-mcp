<?php

namespace App\Integrations\Stripe;

use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\Traits\OauthGatewayTrait;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Stripe\Exception\ExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeOAuth extends AbstractOAuthIntegration implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use OauthGatewayTrait;
    use HasStripeClientTrait;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
        private TenantContext $tenant,
        private UserContext $userContext,
    ) {
        parent::__construct($urlGenerator, $httpClient, $settings);
        $this->stripeSecret = $settings['clientSecret'];
    }

    public function getAuthorizationUrl(string $state): string
    {
        $url = parent::getAuthorizationUrl($state);

        // prefill the Stripe registration form in the URL
        $params = [
            'stripe_landing' => 'register',
            'always_prompt' => 'true',
        ];

        $company = $this->tenant->get();
        $user = $this->userContext->getOrFail();
        $address = $company->address1;
        if ($company->address2) {
            $address .= ', '.$company->address2;
        }

        $params['stripe_user[email]'] = $company->email;
        $params['stripe_user[url]'] = $company->website;
        $params['stripe_user[country]'] = $company->country;
        $params['stripe_user[business_name]'] = $company->name;
        $params['stripe_user[first_name]'] = $user->first_name;
        $params['stripe_user[last_name]'] = $user->last_name;
        $params['stripe_user[street_address]'] = $address;
        $params['stripe_user[city]'] = $company->city;
        $params['stripe_user[state]'] = $company->state;
        $params['stripe_user[zip]'] = $company->postal_code;
        $params['stripe_user[currency]'] = $company->currency;
        $params['stripe_user[phone_number]'] = $company->phone;
        $params['stripe_user[business_type]'] = match ($company->type) {
            'company' => 'corporation',
            'non_profit' => 'non_profit',
            'person' => 'sole_prop',
            default => null,
        };

        return $url.'&'.http_build_query($params);
    }

    public function getAccount(): ?MerchantAccount
    {
        $stripeUserId = $this->lastTokenResult->stripe_user_id;

        return $this->getMerchantAccount(StripeGateway::ID, $stripeUserId);
    }

    /**
     * @param MerchantAccount $account
     */
    protected function customAccountSetup(OAuthAccountInterface $account, ?Request $request): void
    {
        // look up the Stripe account name
        $stripeUserId = $this->lastTokenResult->stripe_user_id;
        $accountName = $this->getAccountName($stripeUserId);

        $this->addMerchantAccount($account, $accountName);

        // Enable relevant payment methods
        $company = $this->tenant->get();
        $this->addPaymentMethod($company, PaymentMethod::CREDIT_CARD, $account);
        if ('US' == $company->country) {
            $this->addPaymentMethod($company, PaymentMethod::ACH, $account);
        }

        $this->addDomainToStripe($stripeUserId, $company);
    }

    /**
     * Gets the connected account's name.
     */
    public function getAccountName(string $accountId): string
    {
        $stripe = $this->getStripe();
        $account = $stripe->accounts->retrieve($accountId);

        if ($name = $account?->settings?->dashboard?->display_name) { /* @phpstan-ignore-line */
            return $name;
        }

        if ($name = $account?->business_profile?->name) { /* @phpstan-ignore-line */
            return $name;
        }

        return $account->email ?? $accountId;
    }

    /**
     * Add merchant account to database.
     */
    private function addMerchantAccount(MerchantAccount $merchantAccount, string $accountName): void
    {
        $merchantAccount->gateway = StripeGateway::ID;
        $merchantAccount->gateway_id = $this->lastTokenResult->stripe_user_id;
        $merchantAccount->name = $accountName;
        $credentials = $merchantAccount->credentials;
        $credentials->user_id = $this->lastTokenResult->stripe_user_id;
        $credentials->publishable_key = $this->lastTokenResult->stripe_publishable_key;
        $credentials->key = $merchantAccount->getToken()->accessToken;
        $merchantAccount->credentials = $credentials;
        $merchantAccount->saveOrFail();
    }

    /**
     * Add payment method to database.
     */
    private function addPaymentMethod(Company $company, string $method, MerchantAccount $merchantAccount): void
    {
        $paymentMethod = PaymentMethod::instance($company, $method);
        $paymentMethod->enabled = true;
        $paymentMethod->setMerchantAccount($merchantAccount);
        $paymentMethod->save();
    }

    /**
     * Add domain to Stripe for Apple Pay.
     */
    private function addDomainToStripe(string $userId, Company $company): void
    {
        // determine customer portal domain out of company url
        $domain = str_replace(['https://', 'http://'], '', $company->url);

        // strip port
        if ($index = strpos($domain, ':')) {
            $domain = substr($domain, 0, $index);
        }

        try {
            $stripe = $this->getStripe();
            $stripe->applePayDomains->create([
                'domain_name' => $domain,
            ], [
                'stripe_account' => $userId,
            ]);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not create Apple Pay domain', ['exception' => $e]);
        }
    }
}
