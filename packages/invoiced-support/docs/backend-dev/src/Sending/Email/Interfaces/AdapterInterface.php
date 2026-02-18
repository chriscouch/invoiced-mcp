<?php

namespace App\Sending\Email\Interfaces;

use App\Sending\Email\Exceptions\SendEmailException;

interface AdapterInterface
{
    /**
     * Checks if this adapter is sending through Invoiced owned infrastructure.
     */
    public function isInvoicedService(): bool;

    /**
     * Sends an email.
     *
     * @throws SendEmailException
     */
    public function send(EmailInterface $message): void;
}
