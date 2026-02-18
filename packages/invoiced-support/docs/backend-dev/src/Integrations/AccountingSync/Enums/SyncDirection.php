<?php

namespace App\Integrations\AccountingSync\Enums;

enum SyncDirection: int
{
    case Read = 1;
    case Write = 2;
}
