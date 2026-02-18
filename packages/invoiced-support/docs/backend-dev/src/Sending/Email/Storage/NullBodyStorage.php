<?php

namespace App\Sending\Email\Storage;

use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;

class NullBodyStorage implements EmailBodyStorageInterface
{
    public function store(InboxEmail $email, string $bodyText, string $type): void
    {
    }

    public function retrieve(InboxEmail $email, string $type): ?string
    {
        return null;
    }
}
