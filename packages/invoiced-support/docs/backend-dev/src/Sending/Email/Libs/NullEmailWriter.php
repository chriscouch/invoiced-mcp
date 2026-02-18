<?php

namespace App\Sending\Email\Libs;

use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Interfaces\OutgoingEmailWriterInterface;

class NullEmailWriter extends AbstractEmailWriter implements OutgoingEmailWriterInterface
{
    public function write(EmailInterface $email): void
    {
    }
}
