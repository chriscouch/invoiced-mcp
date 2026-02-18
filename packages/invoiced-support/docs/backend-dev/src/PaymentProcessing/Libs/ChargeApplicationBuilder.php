<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;
use App\PaymentProcessing\ValueObjects\AppliedCreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\PaymentForm;

final class ChargeApplicationBuilder
{
    /** @var ChargeApplicationItemInterface[] */
    private array $items = [];
    private PaymentFlowSource $source = PaymentFlowSource::CustomerPortal;

    public function setSource(PaymentFlowSource $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Adds the contents of a payment form to the charge application.
     */
    public function addPaymentForm(PaymentForm $form): self
    {
        // Separate credit items from non-credit payment items
        $creditItems = [];
        $nonCreditItems = [];
        foreach ($form->paymentItems as $paymentFormItem) {
            if ($paymentFormItem->amount->isNegative()) {
                $creditItems[] = $paymentFormItem;
            } else {
                $nonCreditItems[] = $paymentFormItem;
            }
        }

        // Apply credits
        $remainingCredit = Money::zero($form->currency);
        $currentCreditItem = null;
        foreach ($nonCreditItems as $paymentFormItem) {
            $itemAmount = $paymentFormItem->amount;

            // apply credit notes while possible
            while (!$itemAmount->isZero()) {
                // apply credit note if available
                if ($remainingCredit->isZero() && count($creditItems)) {
                    $currentCreditItem = array_shift($creditItems);
                    // The amount is negated because credit items are represented as a negative number on payment forms.
                    $remainingCredit = $currentCreditItem->amount->negated();
                }

                if ($remainingCredit->isZero()) {
                    break;
                }

                $toApply = $itemAmount->min($remainingCredit);
                if ($currentCreditItem->document instanceof CreditNote) { /* @phpstan-ignore-line */
                    $this->items[] = new CreditNoteChargeApplicationItem($toApply, $currentCreditItem->document, $paymentFormItem->document); /* @phpstan-ignore-line */
                } else {
                    $this->items[] = new AppliedCreditChargeApplicationItem($toApply, $paymentFormItem->document); /* @phpstan-ignore-line */
                }

                $itemAmount = $itemAmount->subtract($toApply);
                $remainingCredit = $remainingCredit->subtract($toApply);
            }

            // Add the remaining amount of the line item as a payment split
            if (!$itemAmount->isZero()) {
                if ($paymentFormItem->document instanceof Estimate) {
                    $this->items[] = new EstimateChargeApplicationItem($itemAmount, $paymentFormItem->document);
                } elseif ($paymentFormItem->document instanceof Invoice) {
                    $this->items[] = new InvoiceChargeApplicationItem($itemAmount, $paymentFormItem->document);
                } else {
                    $this->items[] = new CreditChargeApplicationItem($itemAmount);
                }
            }
        }

        return $this;
    }

    /**
     * Builds payment application collection based on current builder status.
     */
    public function build(): ChargeApplication
    {
        return new ChargeApplication($this->items, $this->source);
    }
}
