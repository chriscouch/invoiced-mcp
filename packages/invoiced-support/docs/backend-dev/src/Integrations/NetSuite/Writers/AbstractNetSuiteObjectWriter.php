<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\NetSuite\Exceptions\NetSuiteReconciliationException;
use App\Integrations\NetSuite\Interfaces\PathProviderInterface;
use App\Metadata\Interfaces\MetadataModelInterface;

/**
 * Class NetSuiteAdapter
 * Class to adapt Application model
 * to netsuite request.
 *
 * @phpstan-template T of AccountingWritableModelInterface
 */
abstract class AbstractNetSuiteObjectWriter implements PathProviderInterface
{
    /**
     * @phpstan-param T $model
     */
    public function __construct(
        /**
         * @phpstan-var T $model
         */
        protected AccountingWritableModelInterface $model
    ) {
    }

    /**
     * Get url deployment id.
     */
    abstract public function getDeploymentId(): string;

    /**
     * Gets url script id.
     */
    abstract public function getScriptId(): string;

    /**
     * Gets array representation of converted model.
     *
     * @throws NetSuiteReconciliationException
     */
    abstract public function toArray(): ?array;

    /**
     * Sets reverse mapping for imported field.
     *
     * @param object $response - NetSuite response
     */
    public function reverseMap(object $response): void
    {
    }

    /**
     * Gets reverse mapping metadata.
     */
    abstract public function getReverseMapping(): ?string;

    public function skipReconciliation(): void
    {
        $this->model->skipReconciliation();
    }

    /**
     * should send create request.
     */
    public function shouldCreate(): bool
    {
        return null === $this->getReverseMapping();
    }

    /**
     * should send update request.
     */
    public function shouldUpdate(): bool
    {
        return null !== $this->getReverseMapping();
    }

    /**
     * should send delete request.
     */
    public function shouldDelete(): bool
    {
        return false;
    }

    public function getCustomerMapping(Customer $customer): ?string
    {
        $mapping = AccountingCustomerMapping::findForCustomer($customer, IntegrationType::NetSuite);

        return $mapping->accounting_id ?? $this->getMetadataMapping($customer);
    }

    public function getInvoiceMapping(Invoice $invoice): ?string
    {
        $mapping = AccountingInvoiceMapping::findForInvoice($invoice, IntegrationType::NetSuite);

        return $mapping->accounting_id ?? $this->getMetadataMapping($invoice);
    }

    public function getCreditNoteMapping(CreditNote $cn): ?string
    {
        $mapping = AccountingCreditNoteMapping::findForCreditNote($cn, IntegrationType::NetSuite);

        return $mapping->accounting_id ?? $this->getMetadataMapping($cn);
    }

    /** @deprecated metadata fallback */
    private function getMetadataMapping(MetadataModelInterface $item): ?string
    {
        $metadata = $item->metadata;
        if (!property_exists($metadata, NetSuiteWriter::REVERSE_MAPPING)) {
            return null;
        }

        return $metadata->{NetSuiteWriter::REVERSE_MAPPING};
    }
}
