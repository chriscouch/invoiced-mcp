<?php

namespace App\Sending\Email\InboundParse;

use App\Core\Mailer\Mailer;
use App\Sending\Email\Exceptions\InboundParseException;
use App\Sending\Email\InboundParse\Handlers\HandlerInterface;
use App\Sending\Email\ValueObjects\NamedAddress;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Parses all of the possible incoming email addresses
 * to determine how a message should be handled. For example,
 * reply+acmecorp.invoice.1938699@invoicedmail.com would
 * return the comment reply handler.
 */
class Router
{
    /**
     * @param HandlerInterface[] $handlers
     */
    public function __construct(
        private iterable $handlers,
        private readonly RateLimiterFactory $inboxEmailExceptionLimiter
    ) {
    }

    /**
     * @throws InboundParseException when the email address is not recognized
     */
    public function route(string $to): HandlerInterface
    {
        // The To: header can be in RFC 822 format,
        // i.e. Customer Name <reply+acmecorp.invoice.1938699@sandbox.invoicedmail.com>
        $to = (string) self::getAddressRfc822($to);

        foreach ($this->handlers as $handler) {
            if ($handler->supports($to)) {
                return $handler;
            }
        }

        throw new InboundParseException('Mailbox not found: '.$to, 404);
    }

    /**
     * @return NamedAddress[]
     */
    public static function getItemRfc822(string $input): array
    {
        $rfc822Association = mailparse_rfc822_parse_addresses($input);

        return array_map(fn ($email) => new NamedAddress($email['address'], $email['display']), $rfc822Association);
    }

    /**
     * Parses RFC 822 type addresses, i.e.
     * Customer Name <reply+acmecorp.invoice.1938699@sandbox.invoicedmail.com>.
     *
     * This does not conform to the entire specification
     * due to the complexity.
     */
    public static function getAddressRfc822(string $input): ?string
    {
        $response = self::getItemRfc822($input);

        return $response ? $response[0]->getAddress() : null;
    }

    public function notifyAboutException(Mailer $mailer, string $to, string $from, string $originalSubject, InboundParseException $e): void
    {
        $message = "An exception occurred when parsing your email:\n".$e->getMessage();
        $to = self::getAddressRfc822($to);
        $from = self::getAddressRfc822($from);

        // Do not send a notification when the To and From addresses are the
        // same and the error message is that the mailbox that does not exist.
        // This prevents an infinite loop of our mailbox sending to itself.
        if ($to == $from && 404 == $e->getCode()) {
            return;
        }

        // Rate limit how many exceptions an indvidual email can receive from us
        $limiter = $this->inboxEmailExceptionLimiter->create($to);
        if (!$limiter->consume()->isAccepted()) {
            return;
        }

        $mailer->send([
            'from_email' => $to,
            'to' => [['email' => $from, 'name' => '']],
            'subject' => 'RE: '.$originalSubject,
            'text' => $message,
            'html' => nl2br($message),
        ]);
    }
}
