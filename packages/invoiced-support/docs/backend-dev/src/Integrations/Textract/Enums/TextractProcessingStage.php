<?php

namespace App\Integrations\Textract\Enums;

enum TextractProcessingStage: int
{
    case Created = 1;
    case ExpenseProcessed = 2;
    case DocumentProcessed = 3;
    case Succeed = 4;
    case Failed = 5;
}
