<?php

namespace App\Sending\Email\Adapter;

use App\Core\Mailer\EmailBlockList;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Sending\Email\Exceptions\AdapterEmailException;
use App\Sending\Email\Exceptions\EmailLimitException;
use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Libs\EmailSender;
use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;

class AwsAdapter implements AdapterInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const FROM_EMAIL = 'no-reply@invoiced.com';
    private const MAX_ATTACHMENT_SIZE = 9437184; // 9MB, under SES size limit

    public function __construct(
        private SesClient $client,
        private EmailBlockList $blockList,
    ) {
    }

    public function isInvoicedService(): bool
    {
        return true;
    }

    public function send(EmailInterface $message): void
    {
        $message->setAdapterUsed('aws');

        // Check if any recipient is on our internal block list
        $this->blockList->checkForBlockedAddress($message);

        $email = EmailSender::buildEmail($message, self::MAX_ATTACHMENT_SIZE);

        // When sending through our AWS account we need to use an @invoiced.com email as the from address
        $email->from(new Address(self::FROM_EMAIL, (string) $message->getFrom()->getName()));

        $request = [
            'Destinations' => [],
            'RawMessage' => [
                'Data' => $email->toString(),
            ],
        ];

        // Symfony mailer does not include the BCC: header in the data and therefore
        // it must be added here.
        foreach (Envelope::create($email)->getRecipients() as $recipient) {
            $request['Destinations'][] = $recipient->getAddress();
        }

        // AWS has a limit of 50 recipients
        if (count($request['Destinations']) > 50) {
            throw new EmailLimitException('Recipient count cannot exceed 50 recipients');
        }

        try {
            $result = $this->client->sendRawEmail($request);

            // AWS rewrites the message ID
            $messageId = '<'.$result['MessageId'].'@'.$this->client->getRegion().'.amazonses.com>';
            $message->setMessageId($messageId);
        } catch (SesException $e) {
            $this->logger->emergency('Could not send email to customer via SES', ['exception' => $e]);

            throw new AdapterEmailException('Could not send email to customer via SES', $e->getCode(), $e);
        }
    }
}
