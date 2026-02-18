<?php

namespace App\Statements\StatementLines;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\Invoice;
use App\Statements\Interfaces\OpenItemStatementLineInterface;
use App\Statements\StatementLines\OpenItem\OpenCreditNoteStatementLine;
use App\Statements\StatementLines\OpenItem\OpenInvoiceStatementLine;

class OpenItemStatementLineFactory
{
    /**
     * Converts a given model to a statement line, if it
     * should appear on the statement. If it should not
     * appear on the statement then the return of this
     * method will be null.
     *
     * This function assumes the input has been filtered
     * to exclude model types that should not be
     */
    public function make(ReceivableDocument $model): ?OpenItemStatementLineInterface
    {
        if ($model instanceof Invoice) {
            return new OpenInvoiceStatementLine($model);
        }

        if ($model instanceof CreditNote) {
            return new OpenCreditNoteStatementLine($model);
        }

        return null;
    }

    /**
     * Converts a list of models into an array of
     * statement line objects.
     *
     * @param ReceivableDocument[] $models
     *
     * @return OpenItemStatementLineInterface[]
     */
    public function makeFromList(iterable $models): array
    {
        $lines = [];
        foreach ($models as $model) {
            if ($line = $this->make($model)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
