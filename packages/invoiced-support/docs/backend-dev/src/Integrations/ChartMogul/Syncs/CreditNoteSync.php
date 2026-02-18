<?php

namespace App\Integrations\ChartMogul\Syncs;

use App\CashApplication\Models\Transaction;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\ReceivableDocument;
use ChartMogul\Invoice as ChartMogulInvoice;

class CreditNoteSync extends DocumentSync
{
    public static function getDefaultPriority(): int
    {
        return 5;
    }

    protected function getModelClass(): string
    {
        return CreditNote::class;
    }

    public function getDatePaid(ReceivableDocument $document): int
    {
        return (int) max($document->date, Transaction::where('credit_note_id', $document->id)->max('date'));
    }

    public function buildDocumentParams(ReceivableDocument $document, ChartMogulAccount $account): ChartMogulInvoice
    {
        $invoice = parent::buildDocumentParams($document, $account);

        // A credit note on ChartMogul is a negative invoice
        foreach ($invoice->line_items as $lineItem) {
            $lineItem->amount_in_cents *= -1;
            $lineItem->discount_amount_in_cents *= -1;

            if ('subscription' == $lineItem->type) {
                $lineItem->quantity *= -1;
                $lineItem->prorated = true;
            }
        }

        return $invoice;
    }
}
