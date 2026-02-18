<?php

namespace App\CashApplication\Enums;

enum RemittanceAdviceStatus: int
{
    case ReadyToPost = 1;
    case Exception = 2;
    case Posted = 3;
}
