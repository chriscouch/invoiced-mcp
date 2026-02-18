<?php

namespace App\Core\Mailer;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Queue\Queue;
use App\Core\Utils\Compression;
use App\EntryPoint\QueueJob\EmailJob;

class Mailer
{
    public function __construct(
        private Queue $queue,
        private EmailBlockList $blockList
    ) {
    }

    /**
     * Sends an email message.
     */
    public function send(array $message, ?string $template = null, array $templateVars = [], int $retryCounter = 0): void
    {
        $message['to'] = array_values(array_filter($message['to'] ?? [], fn ($recipient) => !$this->blockList->isBlocked($recipient['email'] ?? '')));
        if (empty($message['to'])) {
            return;
        }

        $message = $this->compressMessage($message);
        $variables = $this->compressMessage($templateVars);

        $payload = [
            'm' => $message,
            't' => $template,
            'v' => $variables,
            'r' => $retryCounter,
        ];

        $this->queue->enqueue(EmailJob::class, $payload);
    }

    /**
     * Sends an email to a given user.
     */
    public function sendToUser(User $user, array $message, string $template, array $templateVars = []): void
    {
        $message['to'] = [
            [
                'email' => $user->email,
                'name' => $user->name(true),
            ],
        ];
        $this->send($message, $template, $templateVars);
    }

    /**
     * Sends an email to the administrators in the company.
     */
    public function sendToAdministrators(Company $company, array $message, string $template, array $templateVars = []): void
    {
        /** @var Member[] $members */
        $members = Member::queryWithTenant($company)
            ->where('expires', 0)
            ->all();
        foreach ($members as $member) {
            // A user is considered an "administrator" if they have settings.edit permission
            if ($member->allowed('settings.edit')) {
                $this->sendToUser($member->user(), $message, $template, $templateVars);
            }
        }
    }

    /**
     * Compresses message variables.
     */
    public function compressMessage(array $message): string
    {
        return base64_encode(Compression::compress((string) json_encode($message)));
    }

    /**
     * Decompresses a message.
     */
    public function decompressMessage(string $compressed): array
    {
        return (array) json_decode(Compression::decompress(base64_decode($compressed)), true);
    }
}
