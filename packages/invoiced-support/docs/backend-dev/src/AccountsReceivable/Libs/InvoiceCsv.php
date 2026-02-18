<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use Symfony\Contracts\Translation\TranslatorInterface;

class InvoiceCsv extends ReceivableDocumentCsv
{
    public function __construct(Invoice $invoice, bool $forCustomer, TranslatorInterface $translator)
    {
        parent::__construct($invoice, $forCustomer, $translator);
    }

    public function filename(string $locale): string
    {
        return $this->translator->trans('filenames.invoice', ['%number%' => $this->document->number], 'pdf', $locale).'.csv';
    }

    public function buildDocumentColumns(bool $forCustomer, array $documentCustomFields): array
    {
        $columns = parent::buildDocumentColumns($forCustomer, $documentCustomFields);

        // Due Date
        array_splice($columns, 10, 0, ['due_date']);

        return $columns;
    }

    protected function buildSummaryLine(Customer|Company $contact, array $info, array $documentCustomFields): array
    {
        $summaryLine = parent::buildSummaryLine($contact, $info, $documentCustomFields);

        // Due Date
        array_splice($summaryLine, 10, 0, [($info['due_date']) ? date('Y-m-d', $info['due_date']) : null]);

        return $summaryLine;
    }
}
