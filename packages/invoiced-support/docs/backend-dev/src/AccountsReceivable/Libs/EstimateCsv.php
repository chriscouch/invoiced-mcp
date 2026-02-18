<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Estimate;
use Symfony\Contracts\Translation\TranslatorInterface;

class EstimateCsv extends ReceivableDocumentCsv
{
    public function __construct(Estimate $estimate, bool $forCustomer, TranslatorInterface $translator)
    {
        parent::__construct($estimate, $forCustomer, $translator);
    }

    public function filename(string $locale): string
    {
        return $this->translator->trans('filenames.estimate', ['%number%' => $this->document->number], 'pdf', $locale).'.csv';
    }
}
