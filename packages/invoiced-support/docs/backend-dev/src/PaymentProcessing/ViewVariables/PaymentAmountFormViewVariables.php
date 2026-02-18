<?php

namespace App\PaymentProcessing\ViewVariables;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\ValueObjects\PaymentAmountForm;
use App\PaymentProcessing\ValueObjects\PaymentAmountFormItem;
use Symfony\Component\HttpFoundation\Request;

class PaymentAmountFormViewVariables
{
    public function build(PaymentAmountForm $form, Request $request): array
    {
        // prepare selected items
        $lineItems = array_map(function (PaymentAmountFormItem $lineItem) use ($form, $request) {
            $description = '';
            $document = null;
            $type = $lineItem->nonDocumentType;
            $clientId = $form->customer->client_id;
            $hasMultiple = false;
            $prefilledAmount = null;

            if ($lineItem->document) {
                $type = '';
                if ($lineItem->document instanceof Invoice) {
                    $type = 'invoices';
                    $balance = Money::fromDecimal($lineItem->document->currency, $lineItem->document->balance);
                } elseif ($lineItem->document instanceof CreditNote) {
                    $type = 'creditNotes';
                    $balance = Money::fromDecimal($lineItem->document->currency, $lineItem->document->balance * -1);
                } elseif ($lineItem->document instanceof Estimate) {
                    $type = 'estimates';
                    $balance = $lineItem->document->getDepositBalance();
                } else {
                    $balance = Money::zero($lineItem->document->currency);
                }

                $prefilledAmount = $balance;
                $description = $lineItem->document->number;
                $clientId = $lineItem->document->client_id;
                $document = [
                    'pdf_url' => $lineItem->document->pdf_url.'?locale='.$request->getLocale(),
                    'balance' => $balance->toDecimal(),
                    '_balance' => $balance,
                ];
                $hasMultiple = true;
            } elseif ('creditBalance' == $lineItem->nonDocumentType) {
                $description = 'Credit Balance';
                $prefilledAmount = $lineItem->options[0]['amount'];
            }

            $options = [];
            foreach ($lineItem->options as $option) {
                $options[] = [
                    'name' => $option['type']->name,
                    'value' => $option['type']->value,
                    'currency' => $option['amount']?->currency,
                    'amount' => $option['amount']?->toDecimal(),
                ];
            }

            return [
                'description' => $description,
                'clientId' => $clientId,
                'type' => $type,
                'document' => $document,
                'options' => $options,
                'hasMultiple' => $hasMultiple,
                'prefilledAmount' => $prefilledAmount,
            ];
        }, $form->lineItems);

        return [
            'currency' => $form->currency,
            'currencySymbol' => MoneyFormatter::get()->currencySymbol($form->currency),
            'lineItems' => $lineItems,
        ];
    }
}
