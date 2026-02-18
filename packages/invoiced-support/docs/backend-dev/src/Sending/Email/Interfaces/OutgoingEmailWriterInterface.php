<?php

namespace App\Sending\Email\Interfaces;

interface OutgoingEmailWriterInterface
{
    public function write(EmailInterface $email): void;
}
