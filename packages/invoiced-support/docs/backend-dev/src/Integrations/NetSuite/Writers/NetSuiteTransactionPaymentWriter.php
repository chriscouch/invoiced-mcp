<?php

namespace App\Integrations\NetSuite\Writers;

use App\CashApplication\Libs\TransactionTreeIterator;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\Exception\MismatchedCurrencyException;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;

class NetSuiteTransactionPaymentWriter extends AbstractNetSuiteTransactionWriter
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_payment_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_payment_restlet';
    }

    public function toArray(): ?array
    {
        $model = $this->model;

        $customerMappings = $this->getCustomerMapping($model->customer());
        if (!$customerMappings) {
            return null;
        }

        $invoices = [];
        $amount = new Money($model->currency, 0);
        foreach (TransactionTreeIterator::make($model) as $transaction) {
            $invoices[] = $this->getData($transaction, $amount);
        }

        return [
            'amount' => $amount->toDecimal(),
            'custbody_invoiced_id' => $model->id(),
            'customer' => $customerMappings,
            'invoices' => $invoices,
            'checknum' => $model->gateway_id,
            'gateway' => $model->gateway,
            'payment_source' => $model->payment_source ? $model->payment_source->toString() : null,
            'type' => $model->type,
            'payment' => $model->payment,
        ];
    }

    /**
     * Returns request data from Invoice.
     *
     * @throws NetSuiteReconciliationException
     */
    private function getData(Transaction $transaction, Money &$globalAmount): array
    {
        $type = $transaction->type;
        $amount = Money::fromDecimal($transaction->currency, $transaction->amount);
        if (Transaction::TYPE_ADJUSTMENT === $type) {
            $amount = $amount->negated();
        }
        if (in_array($type, [Transaction::TYPE_PAYMENT, Transaction::TYPE_CHARGE, Transaction::TYPE_ADJUSTMENT])) {
            try {
                $globalAmount = $globalAmount->add($amount);
            } catch (MismatchedCurrencyException $e) {
                throw new NetSuiteReconciliationException($e->getMessage(), ReconciliationError::LEVEL_ERROR);
            }
        }

        $invoice = $transaction->invoice();

        return [
            'amount' => $amount->toDecimal(),
            'id' => $invoice ? $this->getInvoiceMapping($invoice) : null,
            'type' => $type,
        ];
    }
}
