<?php

namespace App\Sending\Email\InboundParse\Handlers;

use App\Sending\Email\Exceptions\InboundParseException;
use Symfony\Component\HttpFoundation\Request;

interface HandlerInterface
{
    /**
     * Returns true if the handler can handle inbound emails
     * to a specific email address. It must be the case that
     * only one handler supports a given email address pattern.
     * It is not possible for multiple handlers to handle an
     * email to a single email address.
     *
     * @throws InboundParseException
     */
    public function supports(string $to): bool;

    /**
     * Handles an inbound email from SendGrid's inbound parse webhook.
     *
     * @throws InboundParseException
     */
    public function processEmail(Request $request): void;
}
