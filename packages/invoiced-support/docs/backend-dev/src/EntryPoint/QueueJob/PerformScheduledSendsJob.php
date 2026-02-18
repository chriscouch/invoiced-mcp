<?php

namespace App\EntryPoint\QueueJob;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\Enums\ChasingChannelEnum;
use App\Chasing\Enums\ChasingTypeEnum;
use App\Chasing\Libs\ChasingStatisticsRepository;
use App\Chasing\Models\ChasingStatistic;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Sending\Email\Libs\EmailSendChannel;
use App\Sending\Interfaces\SendChannelInterface;
use App\Sending\Libs\NullSendChannel;
use App\Sending\Libs\ScheduledSendLock;
use App\Sending\Mail\Libs\LetterSendChannel;
use App\Sending\Models\ScheduledSend;
use App\Sending\Sms\Libs\SmsSendChannel;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;

class PerformScheduledSendsJob extends AbstractResqueJob implements LoggerAwareInterface, StatsdAwareInterface, TenantAwareQueueJobInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const LOCK_TIME = 30; // more than enough time to precess single send
    private NullSendChannel $nullChannel;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Connection $database,
        private readonly EmailSendChannel $emailChannel,
        private readonly SmsSendChannel $smsChannel,
        private readonly LetterSendChannel $letterChannel,
        private readonly LockFactory $lockFactory,
        private readonly ChasingStatisticsRepository $chasingStatisticsRepository)
    {
        $this->nullChannel = new NullSendChannel();
    }

    public function perform(): void
    {
        /** @var ChasingStatistic[] $statistics */
        $statistics = [];
        $scheduledSends = $this->getScheduledSends();
        foreach ($scheduledSends as $send) {
            $id = $send['id'];
            // the ScheduledSend instance is retrieved by id rather than
            // by an ORM iterator query to ensure the most up to date data
            // is used at the time of processing
            $scheduledSend = ScheduledSend::where('id', $id)
                ->oneOrNull();
            if (!($scheduledSend instanceof ScheduledSend) || $scheduledSend->sent) {
                continue;
            }
            // obtain a lock
            $lock = new ScheduledSendLock($this->lockFactory, $this->tenant->get(), $scheduledSend);
            if (!$lock->acquire(self::LOCK_TIME)) {
                continue;
            }

            // Though this is checked in the query, it's possible that
            // a scheduled send marks another scheduled send (which comes
            // after it) as canceled or skipped.
            if ($scheduledSend->attempted()) {
                // do not re-attempt scheduled sends
                $lock->release();
                continue;
            }

            $channel = $this->getSendChannel($scheduledSend->channel);
            $channel->send($scheduledSend);

            if ($scheduledSend->sent) {
                $this->statsd->increment('successful_chasing_action', 1.0, [
                    'chase_level' => 'invoice',
                    'action' => $scheduledSend->getChannel(),
                ]);
                $sendResult = explode(':', (string) $scheduledSend->reference);
                if (count($sendResult) < 3) {
                    $lock->release();
                    continue;
                }
                $delivery = InvoiceDelivery::find($sendResult[1]);
                // invoice and cadence step should be specified
                if (null === $delivery) {
                    $lock->release();
                    continue;
                }

                $attempts = (int) $this->database->fetchOne('SELECT max(attempts) FROM ChasingStatistics WHERE invoice_id=?', [$delivery->invoice->id]);
                $statistic = new ChasingStatistic();
                $statistic->type = ChasingTypeEnum::Invoice->value;
                $statistic->customer_id = $delivery->invoice->customer;
                $statistic->invoice_id = $delivery->invoice->id;
                $statistic->invoice_cadence_id = $delivery->cadence_id;
                $statistic->attempts = $attempts + 1;
                $statistic->channel = ChasingChannelEnum::fromScheduledSend($scheduledSend)->value;
                $statistic->date = CarbonImmutable::now()->toIso8601String();
                $statistics[] = $statistic;

                $lock->release();
                continue;
            }

            if ($scheduledSend->failed) {
                $this->statsd->increment('failed_chasing_action', 1.0, [
                    'chase_level' => 'invoice',
                    'action' => $scheduledSend->getChannel(),
                ]);
            }
        }

        $this->chasingStatisticsRepository->massUpdate($this->tenant->get(), $statistics);
    }

    private function getSendChannel(int $channel): SendChannelInterface
    {
        return match ($channel) {
            ScheduledSend::EMAIL_CHANNEL => $this->emailChannel,
            ScheduledSend::SMS_CHANNEL => $this->smsChannel,
            ScheduledSend::LETTER_CHANNEL => $this->letterChannel,
            default => $this->nullChannel,
        };
    }

    /**
     * Returns a list of ids of ScheduledSends that need to be processed.
     */
    private function getScheduledSends(): array
    {
        return $this->database->createQueryBuilder()
            ->select('id')
            ->from('ScheduledSends')
            ->where('tenant_id = :tid')
            ->andWhere('sent = FALSE')
            ->andWhere('canceled = FALSE')
            ->andWhere('skipped = FALSE')
            ->andWhere('failed = FALSE')
            ->andWhere('(send_after IS NULL OR :current_timestamp >= send_after)')
            ->setParameter('tid', $this->tenant->get()->id())
            ->setParameter('current_timestamp', CarbonImmutable::now()->toDateTimeString())
            ->fetchAllAssociative();
    }
}
