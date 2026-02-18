<?php

namespace App\Sending\Email\Adapter;

use App\Core\Mailer\EmailBlockList;
use App\Core\Utils\DebugContext;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use App\Sending\Email\Exceptions\AdapterEmailException;
use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\SmtpAccount;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Throwable;

class SmtpAdapter implements AdapterInterface
{
    use IntegrationLogAwareTrait;
    use LoggerAwareTrait;

    private const INVOICED_FROM_EMAIL = 'no-reply@invoiced.com';
    private const MAX_ATTACHMENT_SIZE = 20971520; // 20MB, prevent OOM errors

    private MailerInterface $mailer;

    public function __construct(
        private SmtpAccount $smtpAccount,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private EmailBlockList $blockList,
        private bool $isInvoicedService,
    ) {
    }

    public function send(EmailInterface $message): void
    {
        if ($this->isInvoicedService) {
            $message->setAdapterUsed(str_replace('.', '_', $this->smtpAccount->host));

            // Check if any recipient is on our internal block list
            $this->blockList->checkForBlockedAddress($message);
        } else {
            $message->setAdapterUsed('custom_smtp');
        }

        $email = EmailSender::buildEmail($message, self::MAX_ATTACHMENT_SIZE);

        // When sending through our email service providers we need to use an @invoiced.com email as the from address
        if ($this->isInvoicedService) {
            $email->from(new Address(self::INVOICED_FROM_EMAIL, (string) $message->getFrom()->getName()));
        }

        try {
            $this->getMailer()->send($email);
            $this->logSuccessfulSend();
        } catch (Throwable $e) {
            $this->logFailedSend('An error occurred when sending message through SMTP gateway.');
            $logger = $this->makeIntegrationLogger('smtp', $this->smtpAccount->tenant(), $this->cloudWatchLogsClient, $this->debugContext);
            $logger->error($e->getMessage());

            // We expect any exception class to be thrown. For example ErrorException if openssl cannot verify the host's certificate
            if ($e instanceof TransportExceptionInterface && $debug = $e->getDebug()) {
                $logger->info($debug);
            }

            $to = array_map(fn ($to) => $to->getEncodedAddress(), $email->getTo());
            $error = 'Sending email to '.implode(' ', $to).' failed because the SMTP gateway rejected the message. Please confirm that you have the correct values in Settings > Emails > Delivery Settings.';

            throw new AdapterEmailException($error, $e->getCode(), $e);
        }
    }

    public function isInvoicedService(): bool
    {
        return $this->isInvoicedService;
    }

    /**
     * Sets the mailer instance.
     */
    public function setMailer(MailerInterface $mailer): void
    {
        $this->mailer = $mailer;
    }

    /**
     * Gets the mailer instance with the user's SMTP
     * credentials loaded.
     */
    private function getMailer(): MailerInterface
    {
        if (!isset($this->mailer)) {
            // NOTE: the timeout is controlled by the PHP default_socket_timeout ini setting
            $smtpFactory = new EsmtpTransportFactory();
            /** @var EsmtpTransport $transport */
            $transport = $smtpFactory->create($this->smtpAccount->toDsn());

            // By default Symfony Mailer uses the EHLO [127.0.0.1] command to
            // begin the transaction. Google SMTP relay does not like this so
            // we specify a domain to work with this SMTP gateway. For other
            // SMTP gateways this setting should not matter.
            $transport->setLocalDomain('invoiced.com');

            // SSL peer verification is disabled because we often find it problematic
            // with customer email servers. These servers might use an expired or self-signed
            // certificate that cannot be verified but otherwise allows encryption. The preference
            // is to ensure that email sending works over certification verification.
            // Newer versions of symfony/mailer make it possible to configure this in the DSN.
            /** @var SocketStream $stream */
            $stream = $transport->getStream();
            $streamOptions = $stream->getStreamOptions();
            $streamOptions['ssl']['verify_peer'] = false;
            $streamOptions['ssl']['verify_peer_name'] = false;
            $stream->setStreamOptions($streamOptions);

            $this->mailer = new Mailer($transport);
        }

        return $this->mailer;
    }

    /**
     * Logs a successful email send.
     */
    private function logSuccessfulSend(): void
    {
        if ($this->smtpAccount->persisted()) {
            if (!$this->smtpAccount->last_send_successful) {
                $this->smtpAccount->last_send_successful = true;
                $this->smtpAccount->save();
            }
        }
    }

    /**
     * Logs a failing email send.
     */
    private function logFailedSend(string $msg): void
    {
        if ($this->smtpAccount->persisted()) {
            $this->smtpAccount->last_error_message = $msg;
            $this->smtpAccount->last_error_timestamp = time();
            $this->smtpAccount->last_send_successful = false;
            $this->smtpAccount->save();
        }
    }
}
