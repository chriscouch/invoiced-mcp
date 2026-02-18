<?php

namespace App\Sending\Email\Libs;

use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Interfaces\OutgoingEmailWriterInterface;
use App\Sending\Email\ValueObjects\DocumentEmail;

class OutgoingEmailWriterFactory
{
    public function __construct(
        private DocumentEmailWriter $documentEmailWriter,
        private CommonEmailWriter $commonEmailWriter,
        private NullEmailWriter $nullEmailWriter
    ) {
    }

    public function build(EmailInterface $email): OutgoingEmailWriterInterface
    {
        if ($email instanceof DocumentEmail) {
            return $this->documentEmailWriter;
        }

        if ($email->getEmailThread()) {
            return $this->commonEmailWriter;
        }

        return $this->nullEmailWriter;
    }
}
