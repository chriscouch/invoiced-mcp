<?php

namespace App\Sending\Email\ValueObjects;

use App\Sending\Email\Models\SmtpAccount;

final class SmtpCredentials
{
    public function __construct(
        public readonly string $host,
        public readonly string $username,
        public readonly string $password,
        public readonly int $port,
        public readonly string $encryption,
        public readonly string $authMode,
        public readonly ?SmtpAccount $smtpAccount = null
    ) {
    }
}
