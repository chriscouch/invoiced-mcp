<?php

namespace App\Integrations\NetSuite\Writers;

use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use Exception;

class NetSuiteTransactionRefundWriter extends AbstractNetSuiteTransactionWriter
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_refund_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_refund_restlet';
    }

    /**
     * @throws Exception
     */
    public function toArray(): array
    {
        $model = $this->model;
        /** @var Transaction $parent */
        $parent = $model->parentTransaction();
        if (!property_exists($parent->metadata, NetSuiteWriter::REVERSE_MAPPING)) {
            throw new NetSuiteReconciliationException('Skipping refund because the parent payment does not have `netsuite_id` metadata', ReconciliationError::LEVEL_WARNING);
        }

        $amount = $model->amount;

        // NetSuite supports only full refunds
        if ($amount != $parent->paymentAmount()->toDecimal()) {
            throw new NetSuiteReconciliationException('Reconciling partial refunds to NetSuite are not supported', ReconciliationError::LEVEL_WARNING);
        }

        return [
            'amount' => $amount,
            'custbody_invoiced_id' => $model->id(),
            'payment' => $parent->metadata->{NetSuiteWriter::REVERSE_MAPPING},
        ];
    }
}
