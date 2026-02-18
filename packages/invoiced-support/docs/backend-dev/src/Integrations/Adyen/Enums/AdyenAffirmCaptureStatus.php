<?php

namespace App\Integrations\Adyen\Enums;

enum AdyenAffirmCaptureStatus: int
{
    case Created = 1;
    case Captured = 2;

}
