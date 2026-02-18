<?php

namespace App\Sending\Email\Libs;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\Exceptions\AdapterEmailException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\ValueObjects\DocumentEmail;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;

class EmailSender implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private const SENT_CACHE_PREFIX = ':sent_email.';
    private const EMAIL_DEBOUNCE_PERIOD = 300;

    /** @var LockInterface[] */
    private array $locks = [];

    public function __construct(
        private AdapterFactory $adapterFactory,
        private LockFactory $lockFactory,
        private OutgoingEmailWriterFactory $outgoingEmailWriterFactory,
        private string $appDomain,
    ) {
    }

    /**
     * Sends the message.
     *
     * @throws SendEmailException when a message cannot be sent
     */
    public function send(EmailInterface $message): void
    {
        $this->locks = [];

        // Prevent duplicate sending if this is a document message
        if ($message instanceof DocumentEmail) {
            $emailTemplateId = $message->getEmailTemplate()->id;
            $document = $message->getDocument();
            $objectType = $document->getSendObjectType();
            $objectId = $document->getSendId();
            foreach ($message->getTo() as $recipient) {
                // debounce sending to this email address
                $email = $recipient->getAddress();
                $lock = $this->getLock($emailTemplateId, $email, $objectType, $objectId);
                if (!$lock->acquire()) {
                    $this->releaseLocks();

                    return;
                }
            }
        }

        try {
            $this->_send($message);
        } catch (SendEmailException $e) {
            // when there is a send exception we want to
            // release any locks that were just acquired
            // since the recipient did not actually receive anything
            $this->releaseLocks();

            // remove the associated email thread if we end up not sending an email
            if ($thread = $message->getEmailThread()) {
                $thread->deleteIfEmpty();
            }

            throw $e;
        }
    }

    /**
     * @throws SendEmailException
     */
    private function _send(EmailInterface $message): void
    {
        if (0 === count($message->getTo())) {
            throw new SendEmailException('No email recipients given. At least one recipient must be provided.');
        }

        try {
            $this->adapterFactory->get($message)->send($message);
        } catch (SendEmailException $e) {
            if ($e instanceof AdapterEmailException) {
                $this->statsd->increment('email.fail', 1.0, ['transport' => $message->getAdapterUsed(), 'template' => 'customer']);
            }

            throw $e;
        }

        // record a statsd event for each email that was sent
        $this->statsd->increment('email.sent', 1.0, ['transport' => $message->getAdapterUsed(), 'template' => 'customer']);

        $this->outgoingEmailWriterFactory->build($message)->write($message);
    }

    /**
     * Gets the debounce lock for a recipient, sender, and template combo.
     */
    private function getLock(string $emailTemplateId, string $email, ?ObjectType $objectType, int $objectId): LockInterface
    {
        $key = $this->getLockKey($emailTemplateId, $email, $objectType, $objectId);
        if (!isset($this->locks[$key])) {
            $this->locks[$key] = $this->lockFactory->createLock($key, self::EMAIL_DEBOUNCE_PERIOD, false);
        }

        return $this->locks[$key];
    }

    /**
     * Releases the given locks.
     */
    private function releaseLocks(): void
    {
        foreach ($this->locks as $lock) {
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }

        $this->locks = [];
    }

    /**
     * Generates the lock key for a sent email.
     */
    private function getLockKey(string $emailTemplateId, string $email, ?ObjectType $objectType, int $objectId): string
    {
        $key = $this->appDomain;
        $key .= self::SENT_CACHE_PREFIX;
        $key .= md5($emailTemplateId.$email.$objectType?->value.$objectId);

        return $key;
    }

    /**
     * Builds an email that can be sent through the adapter from a message.
     */
    public static function buildEmail(EmailInterface $message, int $maxAttachmentsSize): Email
    {
        $email = (new Email())
            ->from(self::makeAddress($message->getFrom()->getAddress(), (string) $message->getFrom()->getName()))
            ->subject($message->getSubject())
            ->text((string) $message->getPlainText());

        // Recipients
        foreach ($message->getTo() as $address) {
            $emailStr = $address->getAddress();
            if (empty($emailStr)) continue;
            $email->addTo(self::makeAddress($emailStr, (string) $address->getName()));
        }
        foreach ($message->getCc() as $address) {
            $emailStr = $address->getAddress();
            if (empty($emailStr)) continue;
            $email->addCc(self::makeAddress($emailStr, (string) $address->getName()));
        }
        foreach ($message->getBcc() as $address) {
            $emailStr = $address->getAddress();
            if (empty($emailStr)) continue;
            $email->addBcc(self::makeAddress($emailStr, (string) $address->getName()));
        }

        // HTML Content
        if ($html = $message->getHtml(true)) {
            $email->html($html);
        }

        // Headers
        self::copyHeadersIntoEmail($message, $email->getHeaders());

        // Attachments
        $size = 0;
        foreach ($message->getAttachments() as $attachment) {
            $attachmentSize = $attachment->getEncodedSize();
            if (($attachmentSize + $size) > $maxAttachmentsSize) {
                continue;
            }

            // Some email gateways get fussy about the encoding of the attachment. In particular,
            // some characters are problematic like quotes and semicolons.
            $filename = preg_replace('/[^\w\d\s.\-_+\[\]()#{}:|!@$%^&,?=*]+/i', '', $attachment->getFilename());
            $email->attach($attachment->getContent(), $filename, $attachment->getType());
            $size += $attachmentSize;
        }

        return $email;
    }

    public static function copyHeadersIntoEmail(EmailInterface $email, Headers $headers): void
    {
        foreach ($email->getHeaders() as $name => $value) {
            if ('Reply-To' == $name) {
                $headers->addMailboxListHeader($name, [$value]);
            } elseif ('Message-ID' == $name) {
                $headers->addIdHeader($name, Address::create($value)->getAddress());
            } else {
                $headers->addTextHeader($name, $value);
            }
        }
    }

    /**
     * Makes a named recipient.
     */
    private static function makeAddress(string $email, string $name): Address
    {
        // For some reason AWS has a 320 byte limit on the encoded name
        // of the address. When we detect that the encoded header might
        // be too long then the name is excluded to prevent exceeding
        // the 320 byte limit. This check is overly conservative and is
        // generally only triggered when there are large non-ASCII
        // characters in the name. If the header exceeds the maximum
        // length then the name is dropped.
        $address = new Address($email, $name);
        $header = new MailboxListHeader('To', [$address]);
        if (strlen($header->toString()) >= 320) {
            return new Address($email);
        }

        return $address;
    }
}
