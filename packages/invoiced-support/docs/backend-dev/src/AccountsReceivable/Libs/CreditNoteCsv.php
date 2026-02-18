<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\CreditNote;
use Symfony\Contracts\Translation\TranslatorInterface;

class CreditNoteCsv extends ReceivableDocumentCsv
{
    public function __construct(CreditNote $creditNote, bool $forCustomer, TranslatorInterface $translator)
    {
        parent::__construct($creditNote, $forCustomer, $translator);
    }

    public function filename(string $locale): string
    {
        return $this->translator->trans('filenames.credit_note', ['%number%' => $this->document->number], 'pdf', $locale).'.csv';
    }
}
