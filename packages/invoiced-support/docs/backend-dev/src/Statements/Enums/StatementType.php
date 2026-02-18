<?php

namespace App\Statements\Enums;

enum StatementType: string
{
    case BalanceForward = 'balance_forward';
    case OpenItem = 'open_item';
}
