<?php

namespace App\Sending\Email\Adapter;

use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Interfaces\EmailInterface;

class NullAdapter implements AdapterInterface
{
    public function isInvoicedService(): bool
    {
        return false;
    }

    public function send(EmailInterface $message): void
    {
        // the null adapter does not actually send emails.
        // we just pretend the message was sent.
        $message->setAdapterUsed('null');
    }
}
