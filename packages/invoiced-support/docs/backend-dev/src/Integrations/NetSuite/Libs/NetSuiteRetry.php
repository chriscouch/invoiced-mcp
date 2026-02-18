<?php

namespace App\Integrations\NetSuite\Libs;

use App\Core\Multitenant\Exception\MultitenantException;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\ValueObjects\AccountingObjectReference;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\NetSuite\ValueObjects\NetSuiteRetryPathProvider;
use Exception;

/**
 * Retries reconciliation errors that happened when
 * reading from the accounting system.
 */
class NetSuiteRetry
{
    public function __construct(
        private readonly NetSuiteApi $netSuiteApi,
        private readonly TenantContext $context,
    ) {
    }

    /**
     * @throws MultitenantException
     * @throws Exception
     */
    public function retry(array $data): void
    {
        $account = NetSuiteAccount::queryWithTenant($this->context->get())->oneOrNull();
        if (!$account) {
            return;
        }

        try {
            $response = $this->netSuiteApi->callRestlet($account, 'post', new NetSuiteRetryPathProvider(), [
                'id' => $data['accounting_id'],
                'object' => $data['object'],
            ]);
            if ($response && property_exists($response, 'error') && $response->error) {
                ReconciliationError::makeReadError(
                    '',
                    new AccountingObjectReference(IntegrationType::NetSuite, $data['object'], $data['accounting_id'], $data['object_id']),
                    $response->error
                );
            }
        } catch (IntegrationApiException $e) {
            ReconciliationError::makeReadError(
                '',
                new AccountingObjectReference(IntegrationType::NetSuite, $data['object'], $data['accounting_id'], $data['object_id']),
                $e->getMessage()
            );
        }
    }
}
