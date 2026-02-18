<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;

/**
 * @template-extends    AbstractNetSuiteCustomerObjectWriter<Payment>
 *
 * @property Payment $model
 */
class NetSuitePaymentWriter extends AbstractNetSuiteCustomerObjectWriter
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_payment_model_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_payment_model_restlet';
    }

    public function toArray(): array
    {
        $model = $this->model;
        $customer = $model->customer();
        if (!$customer) {
            throw new NetSuiteReconciliationException('Payment has no customer attached', ReconciliationError::LEVEL_WARNING);
        }
        $applied = array_map(function ($applied) {
            if (isset($applied['invoice'])) {
                $invoice = Invoice::findOrFail($applied['invoice']);
                $applied['invoice_netsuite_id'] = $this->getInvoiceMapping($invoice);
                $applied['invoice_number'] = $invoice->number;
            }
            if (isset($applied['credit_note'])) {
                $cn = CreditNote::findOrFail($applied['credit_note']);
                $applied['credit_note_netsuite_id'] = $this->getCreditNoteMapping($cn);
                $applied['credit_note_number'] = $cn->number;
            }

            return $applied;
        }, $model->applied_to);

        $data = parent::toArray();
        $data['applied'] = $applied;

        return $this->backwardCompatibilityDecorator($data);
    }

    /**
     * @deprecated background compatibility with previous version
     */
    private function backwardCompatibilityDecorator(array $data): array
    {
        return array_merge($data, [
            'charge' => $data['charge'] ?? null,
            'voided' => $data['voided'] ?? false,
            'payment_netsuite_id' => $data['netsuite_id'],
            'custbody_invoiced_id' => $data['id'],
            'customer' => $data['parent_customer']['netsuite_id'] ?? null,
            'customer_invoiced_id' => $data['parent_customer']['id'] ?? null,
            'customer_name' => $data['parent_customer']['companyname'] ?? null,
            'customer_number' => $data['parent_customer']['accountnumber'] ?? null,
        ]);
    }

    public function getReverseMapping(): ?string
    {
        return AccountingPaymentMapping::findForPayment($this->model, IntegrationType::NetSuite)?->accounting_id;
    }

    public function shouldUpdate(): bool
    {
        return true;
    }

    protected function getParentCustomer(): ?Customer
    {
        return $this->model->customer();
    }
}
