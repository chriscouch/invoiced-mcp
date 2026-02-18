<?php

namespace App\Integrations\LawPay;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Traits\OauthGatewayTrait;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LawPayOAuth extends AbstractOAuthIntegration
{
    use OauthGatewayTrait;

    private const BASE_LAWPAY = 'https://secure.lawpay.com';

    private const CREDENTIALS_ENDPOINT = '/api/v1/chargeio_credentials';

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
        private string $environment,
        private TenantContext $tenant,
    ) {
        parent::__construct($urlGenerator, $httpClient, $settings);
    }

    public function getRedirectUrl(): string
    {
        return $this->urlGenerator->generate('oauth_finish', ['id' => 'lawpay'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getAuthorizationUrl(string $state): string
    {
        return str_replace(
            '%url%',
            self::BASE_LAWPAY,
            parent::getAuthorizationUrl($state)
        );
    }

    protected function getTokenUrl(): string
    {
        return str_replace(
            '%url%',
            self::BASE_LAWPAY,
            parent::getTokenUrl()
        );
    }

    protected function getRevokeUrl(): string
    {
        return str_replace(
            '%url%',
            self::BASE_LAWPAY,
            parent::getRevokeUrl()
        );
    }

    public function getAccount(): ?MerchantAccount
    {
        // Because of the multi-account capabilities of LawPay,
        // it is not possible to return a merchant account here.
        return null;
    }

    protected function customAccountSetup(OAuthAccountInterface $account, ?Request $request): void
    {
        $credentials = $this->getGatewayCredentials($account->getToken());

        // only pull live accounts in production
        if ('production' === $this->environment) {
            $accounts = $credentials->live_accounts;
            $type = 'live';
        } else {
            $accounts = $credentials->test_accounts;
            $type = 'test';
        }

        if (0 === count($accounts)) {
            throw new OAuthException("We could not connect your LawPay gateway because there are no $type LawPay accounts for us to pull in. Please contact support@invoiced.com for help.");
        }

        $merchantAccounts = [];
        foreach ($accounts as $subAccount) {
            $merchantAccounts[] = $this->saveMerchantAccount($subAccount);
        }

        $this->addPaymentMethod(PaymentMethod::CREDIT_CARD, $merchantAccounts);
        $this->addPaymentMethod(PaymentMethod::ACH, $merchantAccounts);
    }

    /**
     * Fetches the gateway credentials from LawPay given an access token.
     */
    public function getGatewayCredentials(OAuthAccessToken $token): object
    {
        $url = self::BASE_LAWPAY;
        $url .= self::CREDENTIALS_ENDPOINT;

        $response = $this->httpClient->request('GET', $url, [
            'auth_bearer' => $token->accessToken,
        ]);

        return json_decode($response->getContent());
    }

    private function addPaymentMethod(string $method, array $merchantAccounts): void
    {
        // determine the merchant account to use for the default
        $defaultAccount = $this->selectMerchantAccount($merchantAccounts, $method);

        // turn on the specified payment method
        $company = $this->tenant->get();
        $paymentMethod = PaymentMethod::instance($company, $method);
        $paymentMethod->gateway = 'lawpay';
        $paymentMethod->merchant_account = $defaultAccount;
        $paymentMethod->enabled = $defaultAccount ? true : false;
        $paymentMethod->save();
    }

    /**
     * Saves a LawPay account as a merchant account.
     */
    private function saveMerchantAccount(object $account): MerchantAccount
    {
        $gatewayId = $account->id;
        $merchantAccount = $this->getMerchantAccount('lawpay', $gatewayId) ?? $this->makeAccount();

        $merchantAccount->gateway = 'lawpay';
        $merchantAccount->gateway_id = $gatewayId;
        $merchantAccount->name = $account->name;
        $merchantAccount->credentials = (object) [
            'account_id' => $gatewayId,
            'secret_key' => $account->secret_key,
        ];
        $merchantAccount->saveOrFail();

        return $merchantAccount;
    }

    /**
     * Selects the default merchant account.
     */
    public function selectMerchantAccount(array $accounts, string $method): ?int
    {
        // default to the first when there is only 1 account
        if (1 === count($accounts)) {
            return $accounts[0]->id();
        }

        // look for an account with ACH in the name
        // for the ACH method
        if (PaymentMethod::ACH == $method) {
            foreach ($accounts as $account) {
                if (str_contains(strtolower($account->name), 'ach')) {
                    return $account->id();
                }
            }
        }

        // look for an operating account
        foreach ($accounts as $account) {
            if (str_contains(strtolower($account->name), 'operating')) {
                return $account->id();
            }
        }

        return null;
    }
}
