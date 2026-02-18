<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\BillLineItem;
use App\AccountsPayable\Models\PayableDocument;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorCreditLineItem;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

/**
 * @template T of PayableDocument
 */
abstract class VendorDocumentCreateOperation extends VendorDocumentOperation
{
    /**
     * Syncs the document with the ledger.
     *
     * @param T $document
     */
    abstract protected function ledgerSync(PayableDocument $document): void;

    /**
     * @return T
     */
    abstract protected function makeNew(): PayableDocument;

    /**
     * Creates a new document.
     *
     * @throws ModelException
     *
     * @return T
     */
    public function create(array $parameters): PayableDocument
    {
        $document = $this->makeNew();
        $this->saveDocument($document, $parameters);
        $this->ledgerSync($document);

        return $document;
    }

    /**
     * @param T $document
     */
    protected function saveDocument(PayableDocument $document, array $parameters): void
    {
        if (isset($parameters['vendor']) && !$parameters['vendor'] instanceof Vendor) {
            $document->vendor = Vendor::findOrFail($parameters['vendor']);
            unset($parameters['vendor']);
        }

        $desiredWorkflow = $parameters['approval_workflow'] ?? null;
        $desiredWorkflowStep = $parameters['approval_workflow_step'] ?? null;
        unset($parameters['approval_workflow_step'], $parameters['approval_workflow']);

        if (!isset($parameters['date']) || !$parameters['date']) {
            $parameters['date'] = CarbonImmutable::now();
        }

        if (!isset($parameters['currency']) || !$parameters['currency']) {
            $parameters['currency'] = $document->tenant()->currency;
        }

        // Line Items
        $lineItemClass = $document instanceof VendorCredit ? VendorCreditLineItem::class : BillLineItem::class;
        $lineItems = [];
        if (isset($parameters['line_items'])) {
            $total = Money::zero($parameters['currency']);
            $order = 1;
            foreach ($parameters['line_items'] as $lineItem) {
                $lineItemModel = new $lineItemClass();

                foreach ($lineItem as $k => $v) {
                    $lineItemModel->$k = $v;
                }
                $lineItemModel->order = $order;
                ++$order;
                $lineItems[] = $lineItemModel;

                $lineTotal = Money::fromDecimal($total->currency, $lineItem['amount']);
                $total = $total->add($lineTotal);
            }
            $document->total = $total->toDecimal();
            unset($parameters['line_items']);
        }

        foreach ($parameters as $k => $v) {
            $document->$k = $v;
        }

        // we should always get latest workflow version
        $workflow = $this->calculateWorkflow($document, $desiredWorkflow);
        $document->approval_workflow_step = $this->calculateWorkflowStep($document, $workflow, $desiredWorkflowStep);
        $document->approval_workflow = $document->approval_workflow_step?->approval_workflow_path->approval_workflow;

        $document->saveOrFail();

        // Save line items
        $key = $this->getIdField();
        foreach ($lineItems as $lineItem) {
            $lineItem->$key = $document;
            $lineItem->saveOrFail();
        }

        $this->applyTasks($document);
    }
}
