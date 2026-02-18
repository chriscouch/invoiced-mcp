<?php

namespace App\Sending\Email\Interfaces;

use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\InboxEmail;

interface EmailBodyStorageInterface
{
    const TYPE_HEADER = 'headers';
    const TYPE_PLAIN_TEXT = 'plain-text';
    const TYPE_HTML = 'html';

    /**
     * Uploads text content for an email.
     *
     * @throws SendEmailException
     */
    public function store(InboxEmail $email, string $bodyText, string $type): void;

    /**
     * Retrieves the body by the key.
     */
    public function retrieve(InboxEmail $email, string $type): ?string;
}
