<?php

namespace App\Sending\Email\Libs;

use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\AdapterEmailException;
use App\Sending\Email\Exceptions\EmailLimitException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * The email spool is the entry point for emails
 * that need to be sent. It spools emails until the request
 * has finished. Emails are typically sent after
 * a request has completed so that a user does not
 * need to wait for emails to be sent.
 */
class EmailSpool implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    /** @var EmailInterface[] */
    private array $queue = [];

    public function __construct(private EmailSender $emailSender, private DocumentEmailFactory $factory)
    {
    }

    public function __destruct()
    {
        $this->flush();
    }

    /**
     * Spools an email into a memory queue for sending later,
     * generally this happens after the request finishes.
     *
     * @return $this
     */
    public function spool(EmailInterface $email): self
    {
        $this->queue[] = $email;

        return $this;
    }

    /**
     * Spools a document into a memory queue for sending later,
     * generally this happens after the request finishes.
     *
     * @throws SendEmailException when the email cannot be built
     *
     * @return $this
     */
    public function spoolDocument(SendableDocumentInterface $document, EmailTemplate $emailTemplate, array $to = [], bool $throw = true): self
    {
        try {
            return $this->spool(
                $this->factory->make(
                    $document,
                    $emailTemplate,
                    $to ?: $document->getDefaultEmailContacts()
                )
            );
        } catch (SendEmailException $e) {
            if ($throw) {
                throw $e;
            }

            return $this;
        }
    }

    /**
     * Sends all emails in the spool.
     */
    public function flush(): void
    {
        foreach ($this->queue as $email) {
            try {
                $this->emailSender->send($email);
            } catch (SendEmailException $e) {
                if (!$e instanceof AdapterEmailException && !$e instanceof EmailLimitException) {
                    $this->logger->warning('Could not send spooled email', ['exception' => $e]);
                }
            }
        }

        $this->queue = [];
    }

    /**
     * Erases all emails in the spool.
     */
    public function clear(): void
    {
        $this->queue = [];
    }

    public function size(): int
    {
        return count($this->queue);
    }
}
