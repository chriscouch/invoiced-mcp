<?php

namespace App\EntryPoint\CronJob;

use App\Network\Command\SendInvitationEmail;
use App\Network\Models\NetworkInvitation;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class SendInvitationReminders extends AbstractTaskQueueCronJob implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private SendInvitationEmail $sendInviteEmail)
    {
    }

    public static function getName(): string
    {
        return 'network_invitation_reminders';
    }

    public static function getLockTtl(): int
    {
        return 43200; // 12 hours
    }

    public function getTasks(): iterable
    {
        return NetworkInvitation::where('declined', false)
            ->where('expires_at', CarbonImmutable::now()->toDateTimeString(), '>')
            ->all();
    }

    /**
     * @param NetworkInvitation $task
     */
    public function runTask(mixed $task): bool
    {
        try {
            $this->sendInviteEmail->sendNetworkInvitation($task);
        } catch (Throwable $e) {
            $this->logger->error('Could not send invitation reminder', ['exception' => $e]);

            return false;
        }

        return true;
    }
}
