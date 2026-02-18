<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\BillLineItem;
use App\AccountsPayable\Models\PayableDocument;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorCreditLineItem;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Exception\ModelException;

/**
 * @template T of PayableDocument
 */
abstract class VendorDocumentEditOperation extends VendorDocumentOperation
{
    /**
     * Syncs the document with the ledger.
     *
     * @param T $document
     */
    abstract protected function ledgerSync(PayableDocument $document): void;

    /**
     * Edits the given document.
     *
     * @param T $document
     *
     * @throws ModelException
     */
    public function edit(PayableDocument $document, array $parameters): void
    {
        if ($document->voided) {
            throw new ModelException('This document has already been voided.');
        }

        $this->saveDocument($document, $parameters);
        $this->ledgerSync($document);
    }

    protected function determineWorkflow(array $parameters, PayableDocument $document): bool
    {
        // change based on step
        if (array_key_exists('approval_workflow_step', $parameters)) {
            if (null === $parameters['approval_workflow_step']) {
                if (null != $document->approval_workflow_step) {
                    $document->approval_workflow_step = null;
                    $document->approval_workflow = null;

                    return true;
                }
            } else {
                $step = $this->getWorkflowStep($parameters['approval_workflow_step']);
                if ($step->id() !== $document->approval_workflow_step?->id()) {
                    $document->approval_workflow_step = $step;
                    $document->approval_workflow = $document->approval_workflow_step->approval_workflow_path->approval_workflow;

                    return true;
                }
            }
            // change based on workflow
        } elseif (array_key_exists('approval_workflow', $parameters)) {
            if (null === $parameters['approval_workflow']) {
                if (null != $document->approval_workflow) {
                    $document->approval_workflow_step = null;
                    $document->approval_workflow = null;

                    return true;
                }
            } else {
                $workflow = $this->getWorkflow($parameters['approval_workflow']);
                if ($workflow->id() !== $document->approval_workflow?->id()) {
                    $document->approval_workflow_step = $this->calculateWorkflowStep($document, $workflow, null);
                    $document->approval_workflow = $document->approval_workflow_step?->approval_workflow_path->approval_workflow;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param T $document
     *
     * @throws ModelException
     */
    protected function saveDocument(PayableDocument $document, array $parameters): void
    {
        if (isset($parameters['vendor']) && !$parameters['vendor'] instanceof Vendor) {
            $document->vendor = Vendor::findOrFail($parameters['vendor']);
            unset($parameters['vendor']);
        }

        // validate the currency is not being cleared
        if (isset($parameters['currency']) && !$parameters['currency']) {
            throw new ModelException('The currency cannot be unset.');
        }

        $reassignSteps = $this->determineWorkflow($parameters, $document);
        unset($parameters['approval_workflow_step'], $parameters['approval_workflow']);

        if ($reassignSteps) {
            $this->deleteOutDatedTasks($document);
        }

        // Line Items
        $lineItemClass = $document instanceof VendorCredit ? VendorCreditLineItem::class : BillLineItem::class;
        $lineItems = null;
        if (isset($parameters['line_items'])) {
            $lineItems = [];
            $total = Money::zero($document->currency);
            $order = 1;
            $key = $this->getIdField();
            foreach ($parameters['line_items'] as $lineItem) {
                if (isset($lineItem['id'])) {
                    $lineItemModel = $lineItemClass::findOrFail($lineItem['id']);
                } else {
                    $lineItemModel = new $lineItemClass();
                    $lineItemModel->$key = $document;
                }
                unset($lineItem['id']);
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

        $document->saveOrFail();

        // Save line items
        if (is_array($lineItems)) {
            $ids = [];
            foreach ($lineItems as $lineItem) {
                $lineItem->saveOrFail();
                $ids[] = $lineItem->id;
            }
            $this->removeDeletedLineItems($lineItemClass, $document, $this->getIdField(), $ids);
        }

        if ($reassignSteps) {
            $this->applyTasks($document);
        }
    }

    /**
     * @param class-string<MultitenantModel> $lineItemClass
     */
    protected function removeDeletedLineItems(string $lineItemClass, PayableDocument $document, string $key, array $ids): void
    {
        $parentKey = $key.'_id';
        $lineItem = new $lineItemClass();
        $query = $lineItemClass::getDriver()
            ->getConnection(null)
            ->createQueryBuilder()
            ->delete($lineItem->getTablename())
            ->andWhere('tenant_id = '.$document->tenant_id)
            ->andWhere($parentKey.' = '.$document->id);

        // shield saved line items from delete query
        if ($ids) {
            $query->andWhere('id NOT IN ('.implode(',', $ids).')');
        }

        $query->executeStatement();
    }
}
