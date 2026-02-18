<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsPayable\Api\EditAccountsPayableSettingsRoute;
use App\AccountsPayable\Api\RetrieveAccountsPayableSettingsRoute;
use App\AccountsReceivable\Api\EditAccountsReceivableSettingsRoute;
use App\AccountsReceivable\Api\RetrieveAccountsReceivableSettingsRoute;
use App\CustomerPortal\Api\EditCustomerPortalSettingsRoute;
use App\CustomerPortal\Api\RetrieveCustomerPortalSettingsRoute;
use App\CashApplication\Api\EditCashApplicationSettingsRoute;
use App\CashApplication\Api\RetrieveCashApplicationSettingsRoute;
use App\Companies\Api\EditAutoNumberSequenceRoute;
use App\Companies\Api\EditSamlSettingsRoute;
use App\Companies\Api\NextNumberRoute;
use App\Companies\Api\RetrieveSamlSettingsRoute;
use App\Core\Entitlements\Api\EditFeatureRoute;
use App\Sending\Email\Api\CreateSmtpAccountRoute;
use App\Sending\Email\Api\EditSmtpAccountRoute;
use App\Sending\Email\Api\RetrieveSmtpAccountRoute;
use App\Sending\Email\Api\TestSmtpSettingsRoute;
use App\Sending\Email\Models\SmtpAccount;
use App\SubscriptionBilling\Api\EditSubscriptionBillingSettingsRoute;
use App\SubscriptionBilling\Api\RetrieveSubscriptionBillingSettingsRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class SettingsApiController extends AbstractApiController
{
    /*
     * =========
     * Auto Number Sequences API
     * =========
     */
    #[Route(path: '/auto_number_sequences/{model_id}', name: 'retrieve_auto_number_sequence', methods: ['GET'])]
    public function retrieveAutoNumberSequence(NextNumberRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/auto_number_sequences/{model_id}', name: 'edit_auto_number_sequence', methods: ['PATCH'])]
    public function editAutoNumberSequence(EditAutoNumberSequenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Settings API
     * =========
     */
    #[Route(path: '/settings/accounts_receivable', name: 'retrieve_accounts_receivable_settings', methods: ['GET'])]
    public function retrieveAccountsReceivableSettings(RetrieveAccountsReceivableSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/accounts_receivable', name: 'edit_accounts_receivable_settings', methods: ['PATCH'])]
    public function editAccountsReceivableSettings(EditAccountsReceivableSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/accounts_payable', name: 'retrieve_accounts_payable_settings', methods: ['GET'])]
    public function retrieveAccountsPayableSettings(RetrieveAccountsPayableSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/accounts_payable', name: 'edit_accounts_payable_settings', methods: ['PATCH'])]
    public function editAccountsPayableSettings(EditAccountsPayableSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/cash_application', name: 'retrieve_cash_application_settings', methods: ['GET'])]
    public function retrieveCashApplicationSettings(RetrieveCashApplicationSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/cash_application', name: 'edit_cash_application_settings', methods: ['PATCH'])]
    public function editCashApplicationSettings(EditCashApplicationSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/customer_portal', name: 'retrieve_customer_portal_settings', methods: ['GET'])]
    public function retrieveCustomerPortalSettings(RetrieveCustomerPortalSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/customer_portal', name: 'edit_customer_portal_settings', methods: ['PATCH'])]
    public function editCustomerPortalSettings(EditCustomerPortalSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/saml', name: 'settings_saml', methods: ['GET'])]
    public function retrieveSamlSettings(RetrieveSamlSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/saml', name: 'settings_saml_edit', methods: ['POST'])]
    public function editSamlSettings(EditSamlSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/subscription_billing', name: 'retrieve_subscription_billing_settings', methods: ['GET'])]
    public function retrieveSubscriptionBillingSettings(RetrieveSubscriptionBillingSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/settings/subscription_billing', name: 'edit_subscription_billing_settings', methods: ['PATCH'])]
    public function editSubscriptionBillingSettings(EditSubscriptionBillingSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/features/{id}', name: 'edit_feature', methods: ['PATCH'])]
    public function editFeature(EditFeatureRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * SMTP Settings API
     * =========
     */
    #[Route(path: '/smtp_test', name: 'test_smtp_settings', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function testSmtpSettings(TestSmtpSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/smtp_account', name: 'retrieve_smtp_account', methods: ['GET'])]
    public function retrieveSmtpAccount(RetrieveSmtpAccountRoute $route): Response
    {
        if ($account = SmtpAccount::oneOrNull()) {
            $route->setModel($account);
        }

        return $this->runRoute($route);
    }

    #[Route(path: '/smtp_account', name: 'edit_smtp_account', methods: ['PATCH'])]
    public function editSmtpAccount(CreateSmtpAccountRoute $createRoute, EditSmtpAccountRoute $editRoute): Response
    {
        // if the model does not exist then use the create API route,
        // otherwise perform an update
        $account = SmtpAccount::oneOrNull();
        if (!$account) {
            return $this->runRoute($createRoute);
        }

        return $this->runRoute($editRoute->setModel($account));
    }
}
