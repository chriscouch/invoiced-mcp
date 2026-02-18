<?php

namespace App\CashApplication\Libs;

use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;

/**
 * Converts transactions to a normalized array
 * for use in the output of the applied_to property
 * on payments.
 */
class TransactionNormalizer
{
    public function normalize(Transaction $transaction): ?array
    {
        if (Transaction::TYPE_DOCUMENT_ADJUSTMENT === $transaction->type) {
            return $this->normalizeDocumentAdjustmentSplit($transaction);
        }

        if ($transaction->credit_note_id) {
            return $this->normalizeCreditNoteSplit($transaction);
        }

        if (PaymentMethod::BALANCE == $transaction->method && Transaction::TYPE_CHARGE == $transaction->type) {
            return $this->normalizeAppliedCreditSplit($transaction);
        }

        if ($transaction->invoice) {
            return $this->normalizeInvoiceSplit($transaction);
        }

        if ($transaction->estimate) {
            return $this->normalizeEstimateSplit($transaction);
        }

        if (Transaction::TYPE_ADJUSTMENT == $transaction->type) {
            return $this->normalizeCreditSplit($transaction);
        }

        if ('Convenience Fee' == $transaction->notes) {
            return $this->normalizeConvenienceFeeSplit($transaction);
        }

        return null;
    }

    private function normalizeAppliedCreditSplit(Transaction $transaction): array
    {
        $split = [
            'id' => $transaction->id(),
            'type' => PaymentItemType::AppliedCredit->value,
            'amount' => $transaction->amount,
        ];

        if ($transaction->invoice) {
            $split['document_type'] = 'invoice';
            $split['invoice'] = $transaction->invoice;
        } elseif ($transaction->estimate_id) {
            $split['document_type'] = 'estimate';
            $split['estimate'] = $transaction->estimate_id;
        }

        return $split;
    }

    private function normalizeDocumentAdjustmentSplit(Transaction $transaction): array
    {
        $split = [
            'id' => $transaction->id(),
            'type' => PaymentItemType::DocumentAdjustment->value,
            'amount' => $transaction->amount,
        ];

        if ($transaction->credit_note_id) {
            $split['document_type'] = 'credit_note';
            $split['credit_note'] = $transaction->credit_note_id;
        } elseif ($transaction->invoice) {
            $split['document_type'] = 'invoice';
            $split['invoice'] = $transaction->invoice;
        } elseif ($transaction->estimate_id) {
            $split['document_type'] = 'estimate';
            $split['estimate'] = $transaction->estimate_id;
        }

        return $split;
    }

    private function normalizeCreditSplit(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id(),
            'type' => PaymentItemType::Credit->value,
            'amount' => -$transaction->amount, // the amount of adjustments are negative
        ];
    }

    private function normalizeCreditNoteSplit(Transaction $transaction): array
    {
        $split = [
            'id' => $transaction->id(),
            'type' => PaymentItemType::CreditNote->value,
            'amount' => -$transaction->amount, // the amount of adjustments are negative
            'credit_note' => $transaction->credit_note_id,
        ];

        if ($transaction->invoice) {
            $split['document_type'] = 'invoice';
            $split['invoice'] = $transaction->invoice;
        } elseif ($transaction->estimate_id) {
            $split['document_type'] = 'estimate';
            $split['estimate'] = $transaction->estimate_id;
        }

        return $split;
    }

    private function normalizeConvenienceFeeSplit(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id(),
            'type' => PaymentItemType::ConvenienceFee->value,
            'amount' => $transaction->amount,
        ];
    }

    private function normalizeEstimateSplit(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id(),
            'type' => PaymentItemType::Estimate->value,
            'amount' => -$transaction->amount, // the amount of adjustments are negative
            'estimate' => $transaction->estimate_id,
        ];
    }

    private function normalizeInvoiceSplit(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id(),
            'type' => PaymentItemType::Invoice->value,
            'amount' => $transaction->amount,
            'invoice' => $transaction->invoice,
        ];
    }
}
