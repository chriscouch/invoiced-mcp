<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\CreditNote;

class CreditNotePdf extends DocumentPdf
{
    public function __construct(CreditNote $creditNote)
    {
        parent::__construct($creditNote);
    }
}
