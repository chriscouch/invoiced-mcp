<?php

namespace App\Integrations\QuickBooksOnline\Libs;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\Traits\OauthGatewayTrait;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\PaymentProcessing\Gateways\IntuitGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuickBooksOAuth extends AbstractOAuthIntegration
{
    use OauthGatewayTrait;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
        private TenantContext $tenant,
        private string $environment,
        private QuickBooksApi $quickBooksApi,
    ) {
        parent::__construct($urlGenerator, $httpClient, $settings);
    }

    public function getScope(): string
    {
        $scope = 'com.intuit.quickbooks.accounting';

        if ($this->hasPaymentsEnabled()) {
            $scope .= ' com.intuit.quickbooks.payment';
        }

        return $scope;
    }

    public function getAccount(): ?QuickBooksAccount
    {
        return QuickBooksAccount::oneOrNull();
    }

    public function makeAccount(): QuickBooksAccount
    {
        return new QuickBooksAccount();
    }

    /**
     * @param QuickBooksAccount $account
     */
    protected function customAccountSetup(OAuthAccountInterface $account, ?Request $request): void
    {
        if ($request) {
            $account->realm_id = (string) $request->query->get('realmId');
        }

        // get the connected account name
        $this->quickBooksApi->setAccount($account);
        $companyInfo = $this->quickBooksApi->getCompanyInfo();
        $account->name = (string) $companyInfo->CompanyName;

        $company = $this->tenant->get();
        $this->saveAccessToken($company, $account);
    }

    private function hasPaymentsEnabled(): bool
    {
        return MerchantAccount::withoutDeleted()
            ->where('gateway', IntuitGateway::ID)
            ->count() > 0;
    }

    private function saveAccessToken(Company $company, QuickBooksAccount $quickbooksAccount): void
    {
        // Enable Intuit Payments if configured
        if ($this->hasPaymentsEnabled()) {
            // create/update merchant account
            $merchantAccount = $this->connectMerchantAccount($quickbooksAccount);

            // configure payment methods
            $this->addPaymentMethod($company, PaymentMethod::CREDIT_CARD, $merchantAccount);
            $this->addPaymentMethod($company, PaymentMethod::ACH, $merchantAccount);
        }

        // Remove any previous QuickBooks connections
        // with other Invoiced accounts because the connections
        // will no longer work anyways and it causes syncing issues.
        $duplicateAccounts = QuickBooksAccount::queryWithoutMultitenancyUnsafe()
            ->where('tenant_id', $company, '<>')
            ->where('realm_id', $quickbooksAccount->realm_id)
            ->all();
        foreach ($duplicateAccounts as $duplicateAccount) {
            $duplicateAccount->delete();
        }
    }

    /**
     * Gets the QuickBooks account for the given realm ID.
     */
    public function getAccountForRealmId(string $realmId): ?QuickBooksAccount
    {
        return QuickBooksAccount::queryWithoutMultitenancyUnsafe()
            ->where('realm_id', $realmId)
            ->oneOrNull();
    }

    /**
     * Adds/updates Merchant Account in the database.
     */
    private function connectMerchantAccount(QuickBooksAccount $quickBooksAccount): MerchantAccount
    {
        $merchantAccount = $this->getMerchantAccount(IntuitGateway::ID, $quickBooksAccount->realm_id) ?? new MerchantAccount();

        // hydrate and save merchant account
        $merchantAccount->gateway = IntuitGateway::ID;
        $merchantAccount->gateway_id = $quickBooksAccount->realm_id;
        $merchantAccount->name = $quickBooksAccount->name;
        $merchantAccount->credentials = (object) [
            'access_token' => $quickBooksAccount->access_token,
            'test_mode' => 'production' !== $this->environment,
        ];

        $merchantAccount->saveOrFail();

        return $merchantAccount;
    }

    /**
     * Add payment method to database.
     */
    private function addPaymentMethod(Company $company, string $method, MerchantAccount $merchantAccount): void
    {
        $paymentMethod = PaymentMethod::instance($company, $method);

        // make sure we do not overwrite a payment method
        // set up with another payment gateway
        if ($paymentMethod->gateway && IntuitGateway::ID != $paymentMethod->gateway) {
            return;
        }

        $paymentMethod->enabled = true;
        $paymentMethod->setMerchantAccount($merchantAccount);
        $paymentMethod->saveOrFail();
    }

    /**
     * Used for testing.
     */
    public function setQuickBooksApi(QuickBooksApi $quickBooksApi): void
    {
        $this->quickBooksApi = $quickBooksApi;
    }
}
