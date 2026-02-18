<?php

namespace App\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\Estimate;

class EstimatePdf extends DocumentPdf
{
    public function __construct(Estimate $estimate)
    {
        parent::__construct($estimate);
    }
}
