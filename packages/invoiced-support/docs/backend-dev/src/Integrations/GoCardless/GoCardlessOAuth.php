<?php

namespace App\Integrations\GoCardless;

use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\Traits\OauthGatewayTrait;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use GoCardlessPro\Core\Exception\ApiException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This manages the OAuth flow for connecting GoCardless accounts.
 */
class GoCardlessOAuth extends AbstractOAuthIntegration implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use OauthGatewayTrait;

    const GATEWAY_ID = 'gocardless';

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
        private TenantContext $tenant,
        private UserContext $userContext,
    ) {
        parent::__construct($urlGenerator, $httpClient, $settings);
    }

    public function getAuthorizationUrl(string $state): string
    {
        $url = parent::getAuthorizationUrl($state);

        $company = $this->tenant->get();
        $user = $this->userContext->getOrFail();
        $params = [
            'initial_view' => 'signup',
            'language' => $company->language,
            'prefill' => [
                'email' => $company->email,
                'given_name' => $user->first_name,
                'family_name' => $user->last_name,
                'organisation_name' => $company->name,
            ],
        ];

        return $url.'&'.http_build_query($params);
    }

    public function getSuccessRedirectUrl(): string
    {
        // check if the user needs verification
        if ($merchantAccount = $this->getAccount()) {
            $verificationStatus = $this->getVerificationStatus($merchantAccount);
            if ('action_required' == $verificationStatus) {
                return $this->settings['verifyUrl'];
            }
        }

        return parent::getSuccessRedirectUrl();
    }

    public function getAccount(): ?MerchantAccount
    {
        $gatewayId = $this->lastTokenResult->organisation_id;

        return $this->getMerchantAccount(self::GATEWAY_ID, $gatewayId);
    }

    /**
     * @param MerchantAccount $account
     */
    protected function customAccountSetup(OAuthAccountInterface $account, ?Request $request): void
    {
        // save the merchant account and enable the given payment method
        $merchantAccount = $this->saveMerchantAccount($account);
        $this->enablePaymentMethod($merchantAccount);
    }

    /**
     * Gets the verification status of a GoCardless account.
     */
    public function getVerificationStatus(MerchantAccount $merchantAccount): ?string
    {
        $api = new GoCardlessApi();
        $client = $api->getClient($merchantAccount);

        try {
            $creditor = $client->creditors()->list([])->records[0];
        } catch (ApiException) {
            return null;
        }

        return $creditor->verification_status;
    }

    /**
     * Saves a GoCardless account as a merchant account.
     */
    private function saveMerchantAccount(MerchantAccount $merchantAccount): MerchantAccount
    {
        $gatewayId = $this->lastTokenResult->organisation_id;
        $merchantAccount->gateway = self::GATEWAY_ID;
        $merchantAccount->gateway_id = $gatewayId;
        $merchantAccount->name = $this->lastTokenResult->email;
        $credentials = $merchantAccount->credentials;
        $credentials->organisation_id = $gatewayId;
        $credentials->environment = $this->settings['goCardlessEnvironment'];
        $merchantAccount->credentials = $credentials;
        $merchantAccount->saveOrFail();

        return $merchantAccount;
    }

    /**
     * Enables the direct debit payment method.
     *
     * @throws ModelException
     */
    private function enablePaymentMethod(MerchantAccount $merchantAccount): void
    {
        $company = $this->tenant->get();
        $paymentMethod = PaymentMethod::instance($company, PaymentMethod::DIRECT_DEBIT);
        $paymentMethod->setMerchantAccount($merchantAccount);
        $paymentMethod->enabled = true;
        $paymentMethod->saveOrFail();
    }
}
