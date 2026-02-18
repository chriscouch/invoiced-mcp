<?php

namespace App\Companies\Enums;

enum VerificationStatus: string
{
    case Verified = 'verified';
    case NotVerified = 'not_verified';
    case Pending = 'pending';
}
