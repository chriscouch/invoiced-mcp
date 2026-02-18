<?php

namespace App\Integrations\NetSuite\Writers;

use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\Writers\AbstractWriter;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use App\Integrations\NetSuite\Libs\NetSuiteApi;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelDeleted;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Model;

/**
 * Writes data to Netsuite.
 */
class NetSuiteWriter extends AbstractWriter
{
    const REVERSE_MAPPING = 'netsuite_id';

    public function __construct(private NetSuiteApi $netSuiteApi, private NetSuiteWriterFactory $factory)
    {
    }

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return true;
    }

    /**
     * @param NetSuiteAccount $account
     */
    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $netSuiteModel = $this->factory->create($record, $syncProfile);

        // if the entity is already map - we should not create it
        if (!$netSuiteModel->shouldCreate()) {
            return;
        }

        try {
            $data = $netSuiteModel->toArray();
            if (!$data) {
                return;
            }
            $body = $this->netSuiteApi->callRestlet($account, 'post', $netSuiteModel, $data);
            if ($body) {
                $netSuiteModel->skipReconciliation();
                $netSuiteModel->reverseMap($body);
            }
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (NetSuiteReconciliationException $e) {
            // NetSuite integration warnings are being hidden to the user
            if (ReconciliationError::LEVEL_WARNING != $e->getLevel()) {
                $this->handleSyncException($record, $syncProfile->getIntegrationType(), $e->getMessage(), ModelCreated::getName(), $e->getLevel());
            }
        } catch (IntegrationApiException $e) {
            $this->handleApiException($e, $netSuiteModel, $record, ModelCreated::getName(), $syncProfile->getIntegrationType());
        }
    }

    /**
     * @param NetSuiteAccount $account
     */
    public function update(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $netSuiteModel = $this->factory->create($record, $syncProfile);
        if (!$netSuiteModel->shouldUpdate()) {
            return;
        }
        try {
            $data = $netSuiteModel->toArray();
            if (!$data) {
                return;
            }
            $data['id'] = $netSuiteModel->getReverseMapping();

            $this->netSuiteApi->callRestlet($account, 'put', $netSuiteModel, $data);
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (NetSuiteReconciliationException $e) {
            // NetSuite integration warnings are being hidden to the user
            if (ReconciliationError::LEVEL_WARNING != $e->getLevel()) {
                $this->handleSyncException($record, $syncProfile->getIntegrationType(), $e->getMessage(), ModelUpdated::getName(), $e->getLevel());
            }
        } catch (IntegrationApiException $e) {
            $this->handleApiException($e, $netSuiteModel, $record, ModelUpdated::getName(), $syncProfile->getIntegrationType());
        }
    }

    /**
     * @param NetSuiteAccount $account
     */
    public function delete(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $netSuiteModel = $this->factory->create($record, $syncProfile);
        if (!$netSuiteModel->shouldDelete()) {
            return;
        }
        try {
            $this->netSuiteApi->callRestlet($account, 'delete', $netSuiteModel, [
                'id' => $netSuiteModel->getReverseMapping(),
            ]);
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (IntegrationApiException $e) {
            $this->handleApiException($e, $netSuiteModel, $record, ModelDeleted::getName(), $syncProfile->getIntegrationType());
        }
    }

    private function handleApiException(IntegrationApiException $e, AbstractNetSuiteObjectWriter $netSuiteModel, AccountingWritableModelInterface $record, string $eventName, IntegrationType $integrationType): void
    {
        $message = $e->getMessage();
        // special case for legacy NS bundle versions
        if ($netSuiteModel instanceof NetSuiteTransactionPaymentWriter) {
            $error = json_decode($message);
            if ('SSS_INVALID_SCRIPTLET_ID' === $error?->error?->code) {
                $netSuiteModel->skipReconciliation();

                return;
            }
        }
        $this->handleSyncException($record, $integrationType, $message, $eventName);
    }
}
