<?php

namespace App\AccountsPayable\Enums;

enum PayableDocumentSource: int
{
    case Network = 1;
    case Keyed = 2;
    case Imported = 3;
    case InvoiceCapture = 4;
}
