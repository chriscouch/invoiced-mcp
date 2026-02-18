<?php

namespace App\Integrations\Xero\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Xero;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;

class XeroSettingsRoute extends AbstractApiRoute
{
    private const BANK_ACCOUNT = 'BANK';
    private const CLASS_EXPENSE = 'EXPENSE';
    private const CLASS_LIABILITY = 'LIABILITY';
    private const CLASS_REVENUE = 'REVENUE';
    private const STATUS_ACTIVE = 'ACTIVE';

    public function __construct(
        private IntegrationFactory $integrations,
        private XeroApi $xeroApi,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            features: ['accounting_sync'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Xero $integration */
        $integration = $this->integrations->get(IntegrationType::Xero, $this->tenant->get());
        if (!$integration->isConnected()) {
            throw new InvalidRequest('Xero account is not connected', 404);
        }

        // refresh the xero access token before starting
        /** @var XeroAccount $xeroAccount */
        $xeroAccount = $integration->getAccount();
        $this->xeroApi->setAccount($xeroAccount);

        // fetch the chart of accounts and tax rates
        try {
            $chartOfAccounts = $this->xeroApi->getMany('Accounts');
        } catch (IntegrationApiException $e) {
            throw new ApiError($e->getMessage());
        }

        // parse the chart of accounts into each type we need
        $bankAccounts = [];
        $salesAccounts = [];
        $expenseAccounts = [];
        $liabilityAccounts = [];
        foreach ($chartOfAccounts as $account) {
            // accounts must have a user-defined code
            // in order to be used by the accounting sync
            $code = $account->Code ?? '';
            if (!$code) {
                continue;
            }

            // accounts must be active, not archived
            if (self::STATUS_ACTIVE != $account->Status) {
                continue;
            }

            if (self::CLASS_EXPENSE == $account->Type) {
                $expenseAccounts[] = $account;
            }

            if (self::CLASS_REVENUE == $account->Class) {
                $salesAccounts[] = $account;
            }

            if (self::CLASS_LIABILITY == $account->Class) {
                $liabilityAccounts[] = $account;
            }

            if (self::BANK_ACCOUNT == $account->Type || $account->EnablePaymentsToAccount) {
                $bankAccounts[] = $account;
            }
        }

        return [
            'expense_accounts' => $expenseAccounts,
            'sales_accounts' => $salesAccounts,
            'liability_accounts' => $liabilityAccounts,
            'bank_accounts' => $bankAccounts,
        ];
    }
}
