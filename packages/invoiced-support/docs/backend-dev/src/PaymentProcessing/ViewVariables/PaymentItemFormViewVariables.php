<?php

namespace App\PaymentProcessing\ViewVariables;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Core\I18n\ValueObjects\Money;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\ValueObjects\PaymentItemsForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentItemFormViewVariables
{
    use CustomerPortalViewVariablesTrait;

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function build(PaymentItemsForm $form, Request $request): array
    {
        // sort ASC
        $allDocuments = $form->documents;
        usort($allDocuments, function (ReceivableDocument $b, ReceivableDocument $a) {
            if ($a->date != $b->date) {
                return $a->date <=> $b->date;
            }

            return $a->object <=> $b->object;
        });

        $fields = CustomField::where('object', ['credit_note', 'estimate', 'invoice'])
            ->where('external', true)
            ->all();
        $fieldsKeys = [
            'credit_note' => [],
            'estimate' => [],
            'invoice' => [],
        ];

        foreach ($fields as $field) {
            $fieldsKeys[$field['object']][] = $field['id'];
        }

        // prepare selected items
        $dateFormat = $form->company->date_format;
        $allDocuments = array_map(function (ReceivableDocument $document) use ($dateFormat, $form, $request, $fieldsKeys) {
            $total = $document->total;
            $dueDate = null;
            $balance = 0;
            $type = '';
            $metadata = [];
            if ($document instanceof Invoice) {
                $type = 'Invoice';
                $balance = $document->balance;
                $dueDate = $document->due_date ? date($dateFormat, $document->due_date) : null;
                $metadata = array_intersect_key((array) $document->metadata, array_flip($fieldsKeys['invoice']));
            } elseif ($document instanceof CreditNote) {
                $type = 'CreditNote';
                $total *= -1;
                $balance = $document->balance * -1;
                $metadata = array_intersect_key((array) $document->metadata, array_flip($fieldsKeys['credit_note']));
            } elseif ($document instanceof Estimate) {
                $type = 'Quote';
                $balance = $document->deposit;
                $metadata = array_intersect_key((array) $document->metadata, array_flip($fieldsKeys['estimate']));
            }

            $balance = Money::fromDecimal($document->currency, $balance);
            $total = Money::fromDecimal($document->currency, $total);

            return [
                'clientId' => $document->client_id,
                'currency' => $document->currency,
                'date' => date($dateFormat, $document->date),
                'number' => $document->number,
                'purchaseOrder' => $document->purchase_order,
                'type' => $type,
                'status' => $document->status,
                'pdf_url' => $document->pdf_url.'?locale='.$request->getLocale(),
                'due_date' => $dueDate,
                'total' => $total->toDecimal(),
                '_total' => $total,
                'balance' => $balance->toDecimal(),
                '_balance' => $balance,
                'selected' => $form->isDocumentSelected($document),
                'metadata' => $metadata,
            ];
        }, $allDocuments);

        // add credit balance
        if ($form->creditBalance->isPositive()) {
            $creditBalance = $form->creditBalance->negated();
            $creditBalanceLine = [
                'clientId' => '1',
                'date' => null,
                'number' => 'Credit Balance',
                'purchaseOrder' => null,
                'type' => 'CreditBalance',
                'status' => null,
                'pdf_url' => null,
                'due_date' => null,
                'currency' => $creditBalance->currency,
                'total' => $creditBalance->toDecimal(),
                '_total' => $creditBalance,
                'balance' => $creditBalance->toDecimal(),
                '_balance' => $creditBalance,
                'selected' => $form->isCreditBalanceSelected(),
            ];
            array_splice($allDocuments, 0, 0, [$creditBalanceLine]);
        }

        // build the totals
        $totals = $this->calculateTotals($allDocuments, ['total', 'balance'], $form->customer->calculatePrimaryCurrency());

        return [
            'advancePaymentSelected' => $form->isAdvancePaymentSelected(),
            'allowAdvancePayment' => $form->advancePayment,
            'clientId' => $form->customer->client_id,
            'documents' => $allDocuments,
            'totals' => $totals,
        ];
    }
}
