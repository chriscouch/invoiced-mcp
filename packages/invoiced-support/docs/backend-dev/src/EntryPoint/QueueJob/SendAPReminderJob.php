<?php

namespace App\EntryPoint\QueueJob;

use App\Companies\Models\Member;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Notifications\ValueObjects\APReminder;
use Doctrine\DBAL\Connection;

/**
 * Sends AP document assignment notification summary.
 */
class SendAPReminderJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, StatsdAwareInterface, MaxConcurrencyInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private readonly Mailer $mailer,
        private readonly Connection $connection,
    ) {
    }

    public function perform(): void
    {
        $userId = $this->args['user_id'];
        $member = Member::where('user_id', $userId)->oneOrNull();
        if (!$member) {
            return;
        }

        $bills = $this->connection->createQueryBuilder()->select('b.number', 'v.name as vendor', 't.bill_id as id')
            ->from('Tasks', 't')
            ->join('t', 'Bills', 'b', 't.bill_id = b.id')
            ->join('b', 'Vendors', 'v', 'b.vendor_id = v.id')
            ->andWhere('t.bill_id IS NOT NULL')
            ->andWhere('t.user_id = :uid')
            ->andWhere('t.action = :action')
            ->andWhere('t.complete = 0')
            ->setParameter('action', 'approve_bill')
            ->setParameter('uid', $userId)
            ->fetchAllAssociative();

        $credits = $this->connection->createQueryBuilder()->select('b.number', 'v.name as vendor', 't.vendor_credit_id as id')
            ->from('Tasks', 't')
            ->join('t', 'VendorCredits', 'b', 't.vendor_credit_id = b.id')
            ->join('b', 'Vendors', 'v', 'b.vendor_id = v.id')
            ->andWhere('t.vendor_credit_id IS NOT NULL')
            ->andWhere('t.user_id = :uid')
            ->andWhere('t.action = :action')
            ->andWhere('t.complete = 0')
            ->setParameter('action', 'approve_vendor_credit')
            ->setParameter('uid', $userId)
            ->fetchAllAssociative();

        $items = array_merge($bills, $credits);

        if (!$items) {
            return;
        }

        $reminder = new APReminder($items);

        $this->mailer->sendToUser(
            $member->user(),
            [
                'subject' => $reminder->getSubject(),
            ],
            $reminder->getTemplate($items),
            $reminder->getVariables(),
        );

        $this->statsd->increment('ap_task_reminder.sent', 1.0, ['member_id' => $member->id()]);
    }

    public static function getMaxConcurrency(array $args): int
    {
        // 20 jobs for all accounts.
        return 20;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'process_ap_reminder';
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
