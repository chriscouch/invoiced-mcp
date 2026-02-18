<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Api\CreateAccountingSyncProfileRoute;
use App\Integrations\AccountingSync\Api\EditAccountingSyncProfile;
use App\Integrations\AccountingSync\Api\EnqueueAccountingSyncRoute;
use App\Integrations\AccountingSync\Api\ListReconciliationErrorsRoute;
use App\Integrations\AccountingSync\Api\RetrieveAccountingSyncStatusRoute;
use App\Integrations\AccountingSync\Api\RetryReconciliationRoute;
use App\Integrations\Api\CreateReconciliationErrorRoute;
use App\Integrations\Api\DeleteReconciliationErrorRoute;
use App\Integrations\Api\DisconnectIntegrationRoute;
use App\Integrations\Api\ListIntegrationsRoute;
use App\Integrations\Api\RetrieveIntegrationRoute;
use App\Integrations\Avalara\Api\ConnectAvalaraRoute;
use App\Integrations\Avalara\Api\ListAvalaraCompaniesRoute;
use App\Integrations\BusinessCentral\Api\BusinessCentralSettingsRoute;
use App\Integrations\ChartMogul\Api\ConnectChartMogulRoute;
use App\Integrations\EarthClassMail\Api\ConnectEarthClassMailRoute;
use App\Integrations\EarthClassMail\Api\ListEarthClassMailInboxesRoute;
use App\Integrations\EarthClassMail\Api\StartEarthClassMailSyncRoute;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Api\ConnectIntacctRoute;
use App\Integrations\Intacct\Api\CreateIntacctSyncProfileRoute;
use App\Integrations\Intacct\Api\EditIntacctSyncProfileRoute;
use App\Integrations\Intacct\Api\IntacctOrderEntryDocumentTypesRoute;
use App\Integrations\Intacct\Api\IntacctSettingsRoute;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Lob\ConnectLobRoute;
use App\Integrations\NetSuite\Api\ConnectNetSuiteRoute;
use App\Integrations\Plaid\Api\CreatePlaidLinkTokenRoute;
use App\Integrations\Plaid\Api\CreatePlaidUpgradeLinkTokenRoute;
use App\Integrations\Plaid\Api\FinishVerifyPlaidLinkRoute;
use App\Integrations\QuickBooksDesktop\Api\ConnectQuickBooksDesktopRoute;
use App\Integrations\QuickBooksDesktop\Api\ListSyncsRoute;
use App\Integrations\QuickBooksDesktop\Api\SkipRecordRoute;
use App\Integrations\QuickBooksDesktop\Api\StopSyncRoute;
use App\Integrations\QuickBooksDesktop\Api\SyncedRecordsRoute;
use App\Integrations\QuickBooksOnline\Api\CreateQuickBooksOnlineSyncProfileRoute;
use App\Integrations\QuickBooksOnline\Api\EditQuickBooksOnlineSyncProfileRoute;
use App\Integrations\QuickBooksOnline\Api\QuickBooksOnlineSettingsRoute;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\Twilio\CreateTwilioAccountRoute;
use App\Integrations\Twilio\EditTwilioAccountRoute;
use App\Integrations\Twilio\TwilioAccount;
use App\Integrations\Twilio\TwilioSettingsRoute;
use App\Integrations\Workato\Api\WorkatoSchemaRoute;
use App\Integrations\Xero\Api\CreateXeroSyncProfileRoute;
use App\Integrations\Xero\Api\EditXeroSyncProfileRoute;
use App\Integrations\Xero\Api\XeroSettingsRoute;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Zapier\Api\SubscribeRoute;
use App\Integrations\Zapier\Api\UnsubscribeRoute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class IntegrationsApiController extends AbstractApiController
{
    /*
     * =========
     * Accounting Sync API
     * =========
     */
    #[Route(path: '/accounting_sync_profiles', name: 'create_accounting_sync_profile', methods: ['POST'])]
    public function createAccountingSyncProfile(CreateAccountingSyncProfileRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/accounting_sync_profiles/{model_id}', name: 'edit_accounting_sync_profile', methods: ['PATCH'])]
    public function editAccountingSyncProfile(EditAccountingSyncProfile $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reconciliation_errors', name: 'list_reconciliation_errors', methods: ['GET'])]
    public function listReconciliationErrors(ListReconciliationErrorsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reconciliation_errors/{model_id}/retry', name: 'retry_reconciliation', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function retryReconciliation(RetryReconciliationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reconciliation_errors/{model_id}', name: 'delete_reconciliation_error', methods: ['DELETE'])]
    public function deleteReconciliationError(DeleteReconciliationErrorRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/reconciliation_errors', name: 'create_reconciliation_error', methods: ['POST'])]
    public function createReconciliationError(CreateReconciliationErrorRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Avalara API
     * =========
     */
    #[Route(path: '/integrations/avalara', name: 'connect_avalara', methods: ['POST'])]
    public function connectAvalara(ConnectAvalaraRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/avalara/companies', name: 'list_avalara_companies', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function listAvalaraCompanies(ListAvalaraCompaniesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Business Central API
     * =========
     */
    #[Route(path: '/integrations/business_central/settings', name: 'retrieve_business_central_settings', methods: ['GET'])]
    public function retrieveBusinessCentralSettings(BusinessCentralSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * ChartMogul API
     * =========
     */
    #[Route(path: '/integrations/chartmogul', name: 'connect_chartmogul', methods: ['POST'])]
    public function connectChartMogul(ConnectChartMogulRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Earth Class Mail API
     * =========
     */
    #[Route(path: '/integrations/earth_class_mail', name: 'connect_ecm', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function connectEarthClassMail(ConnectEarthClassMailRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/earth_class_mail/inboxes', name: 'list_ecm_inboxes', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function listEarthClassMailInboxes(ListEarthClassMailInboxesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/earth_class_mail/enqueue_sync', name: 'ecm_enqueue_sync', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function syncEarthClassMail(StartEarthClassMailSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Intacct API
     * =========
     */
    #[Route(path: '/integrations/intacct', name: 'connect_intacct', methods: ['POST'])]
    public function connectIntacct(ConnectIntacctRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/intacct/settings', name: 'retrieve_intacct_settings', methods: ['GET'])]
    public function retrieveIntacctSettings(IntacctSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/intacct/sync_profile', name: 'edit_intacct_settings', methods: ['PATCH'])]
    public function editIntacctSyncProfile(CreateIntacctSyncProfileRoute $createRoute, EditIntacctSyncProfileRoute $editRoute): Response
    {
        // if the model does not exist then use the create API route,
        // otherwise perform an update
        $account = IntacctSyncProfile::oneOrNull();
        if (!$account) {
            return $this->runRoute($createRoute);
        }

        return $this->runRoute($editRoute->setModel($account));
    }

    #[Route(path: '/integrations/intacct/sales_document_types', name: 'retrieve_intacct_sales_document_types', methods: ['GET'])]
    public function retrieveIntacctSalesDocumentTypes(IntacctOrderEntryDocumentTypesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Integrations API
     * =========
     */
    #[Route(path: '/integrations', name: 'list_integrations', methods: ['GET'])]
    public function listIntegrations(ListIntegrationsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/syncs', name: 'list_syncs', methods: ['GET'])]
    public function listSyncs(ListSyncsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/{id}', name: 'retrieve_integration', methods: ['GET'])]
    public function retrieveIntegration(RetrieveIntegrationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/{id}/enqueue_sync', name: 'enqueue_accounting_sync', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function enqueueAccountingSync(EnqueueAccountingSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/{id}/sync_status', name: 'retrieve_integration_sync_status', methods: ['GET'])]
    public function retrieveIntegrationSyncStatus(RetrieveAccountingSyncStatusRoute $route, string $id): Response
    {
        return $this->runRoute($route->setIntegration(IntegrationType::fromString($id)));
    }

    #[Route(path: '/integrations/syncs/{id}', name: 'stop_syncs', methods: ['DELETE'], defaults: ['no_database_transaction' => true])]
    public function stopSync(StopSyncRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/syncs/skipped_records', name: 'create_sync_skipped_records', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function createSyncSkippedRecord(SkipRecordRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/syncs/{id}/records', name: 'retrieve_synced_records', methods: ['GET'])]
    public function retrieveSyncedRecords(SyncedRecordsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/{id}', name: 'disconnect_integration', methods: ['DELETE'], defaults: ['no_database_transaction' => true])]
    public function disconnectIntegration(DisconnectIntegrationRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Lob API
     * =========
     */
    #[Route(path: '/integrations/lob', name: 'edit_lob_settings', methods: ['POST'])]
    public function editLobSettings(ConnectLobRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * NetSuite API
     * =========
     */
    #[Route(path: '/integrations/netsuite', name: 'connect_netsuite', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function connectNetSuite(ConnectNetSuiteRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Plaid API
     * =========
     */
    #[Route(path: '/integrations/plaid/create_link_token', name: 'create_plaid_link_token', methods: ['POST'])]
    public function createPlaidLinkToken(CreatePlaidLinkTokenRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plaid_links/{model_id}/create_link_token', name: 'create_plaid_upgrade_link_token', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function createUpgradeLinkToken(CreatePlaidUpgradeLinkTokenRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/plaid_links/{model_id}/verification', name: 'finish_verification_plaid_links', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function finishVerifyLink(FinishVerifyPlaidLinkRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * QuickBooks Desktop API
     * =========
     */
    #[Route(path: '/integrations/quickbooks_desktop', name: 'generate_quickbooks_desktop_config', methods: ['POST'])]
    public function generateQuickBooksDesktopConfig(ConnectQuickBooksDesktopRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * QuickBooks Online API
     * =========
     */
    #[Route(path: '/integrations/quickbooks_online/settings', name: 'retrieve_quickbooks_online_settings', methods: ['GET'])]
    public function retrieveQuickBooksOnlineSettings(QuickBooksOnlineSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/quickbooks_online/sync_profile', name: 'edit_quickbooks_online_settings', methods: ['PATCH'])]
    public function editQuickBooksOnlineSyncProfile(CreateQuickBooksOnlineSyncProfileRoute $createRoute, EditQuickBooksOnlineSyncProfileRoute $editRoute): Response
    {
        // if the model does not exist then use the create API route,
        // otherwise perform an update
        $account = QuickBooksOnlineSyncProfile::oneOrNull();
        if (!$account) {
            return $this->runRoute($createRoute);
        }

        return $this->runRoute($editRoute->setModel($account));
    }

    /*
     * =========
     * Twilio API
     * =========
     */
    #[Route(path: '/integrations/twilio/settings', name: 'retrieve_twilio_settings', methods: ['GET'])]
    public function retrieveTwilioSettings(TwilioSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/twilio', name: 'edit_twilio_settings', methods: ['POST'])]
    public function editTwilioSettings(CreateTwilioAccountRoute $createRoute, EditTwilioAccountRoute $editRoute): Response
    {
        // if the model does not exist then use the create API route,
        // otherwise perform an
        $account = TwilioAccount::oneOrNull();
        if (!$account) {
            return $this->runRoute($createRoute);
        }

        return $this->runRoute($editRoute->setModel($account));
    }

    /*
     * =========
     * Workato API
     * =========
     */
    #[Route(path: '/integrations/workato/{object}', name: 'workato_schema', methods: ['GET'])]
    public function getObjectSchema(WorkatoSchemaRoute $route): Response
    {
        return $this->runRoute($route);
    }

    /*
     * =========
     * Xero API
     * =========
     */
    #[Route(path: '/integrations/xero/settings', name: 'retrieve_xero_settings', methods: ['GET'])]
    public function retrieveXeroSettings(XeroSettingsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/integrations/xero/sync_profile', name: 'edit_xero_settings', methods: ['PATCH'])]
    public function editXeroSyncProfile(CreateXeroSyncProfileRoute $createRoute, EditXeroSyncProfileRoute $editRoute): Response
    {
        // if the model does not exist then use the create API route,
        // otherwise perform an update
        $account = XeroSyncProfile::oneOrNull();
        if (!$account) {
            return $this->runRoute($createRoute);
        }

        return $this->runRoute($editRoute->setModel($account));
    }

    /*
     * =========
     * Zapier API
     * =========
     */
    #[Route(path: '/zapier/subscribe', name: 'zapier_subscribe', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function zapierSubscribe(SubscribeRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/zapier/unsubscribe', name: 'zapier_unsubscribe', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function zapierUnsubscribe(UnsubscribeRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/zapier/ping', name: 'zapier_ping', methods: ['GET'])]
    public function zapierPing(TenantContext $tenant): JsonResponse
    {
        return new JsonResponse([
            'company_name' => $tenant->get()->name,
        ]);
    }
}
