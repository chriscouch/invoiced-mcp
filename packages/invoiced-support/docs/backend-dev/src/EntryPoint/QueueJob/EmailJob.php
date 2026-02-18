<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Mailer\Mailer;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailJob extends AbstractResqueJob implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    private const MAX_RETRIES = 3;

    public function __construct(
        private Mailer $spooler,
        private MailerInterface $mailer,
        private string $defaultFromEmail,
        private string $defaultFromName,
        private string $projectDir,
    ) {
    }

    public function perform(): void
    {
        // uncompress the message
        $message = $this->spooler->decompressMessage($this->args['m']);

        // decode attachments
        if (isset($message['attachments']) && is_array($message['attachments'])) {
            foreach ($message['attachments'] as &$attachment) {
                $attachment['content'] = base64_decode($attachment['content']);
            }
        }

        // uncompress the message variables
        $templateVariables = $this->spooler->decompressMessage($this->args['v']);

        if ($email = $this->buildEmail($message, $this->args['t'], $templateVariables)) {
            try {
                $this->mailer->send($email);

                // record a statsd event a sent email
                $this->statsd->increment('email.sent', 1.0, ['transport' => 'symfony', 'template' => $this->args['t'] ?: 'none']);
            } catch (TransportExceptionInterface $e) {
                // record a statsd event a failed email
                $this->statsd->increment('email.fail', 1.0, ['transport' => 'symfony', 'template' => $this->args['t'] ?: 'none']);

                // Retry on transport failures
                $retryCounter = $this->args['r'] ?? 0;
                if ($retryCounter < self::MAX_RETRIES) {
                    ++$retryCounter;
                    $this->spooler->send($message, $this->args['t'], $templateVariables, $retryCounter);
                } else {
                    throw $e;
                }
            }
        }
    }

    private function buildEmail(array $message, ?string $template, array $templateVariables): ?Email
    {
        if (!is_array($message['to']) || 0 == count($message['to'])) {
            return null;
        }

        // set missing from information
        if (!isset($message['from_email'])) {
            $message['from_email'] = $this->defaultFromEmail;
        }

        if (!isset($message['from_name'])) {
            $message['from_name'] = $this->defaultFromName;
        }

        // build email
        $email = (new TemplatedEmail())
            ->from(new Address($message['from_email'], $message['from_name']))
            ->subject($message['subject']);

        // build recipients
        foreach ($message['to'] as $item) {
            $email->addTo(new Address($item['email'], (string) $item['name']));
        }

        // build email body
        if (isset($message['html'])) {
            $email->html($message['html']);
        } elseif ($template) {
            $htmlTemplate = 'emails/'.$template.'.twig';
            $email->htmlTemplate($htmlTemplate)
                ->context($templateVariables);
        }

        if (isset($message['text'])) {
            $email->text($message['text']);
        } elseif ($template) {
            $textTemplate = 'emails/text/'.$template.'.twig';
            // The text template is optional and may only be
            // included if it exists.
            if (file_exists($this->projectDir.'/templates/'.$textTemplate)) {
                $email->textTemplate($textTemplate)
                    ->context($templateVariables);
            }
        }

        // build attachments
        if (isset($message['attachments']) && is_array($message['attachments'])) {
            foreach ($message['attachments'] as $attachment) {
                $email->attach($attachment['content'], $attachment['name'], $attachment['type']);
            }
        }

        if (isset($message['reply_to_email'])) {
            $email->replyTo(new Address($message['reply_to_email'], $message['reply_to_name'] ?? $message['from_name']));
        }

        // This header tells email clients to not send any auto replies.
        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'All');

        return $email;
    }
}
