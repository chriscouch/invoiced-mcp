<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\InvoiceChasing\InvoiceChaseTimelineBuilder;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Chasing\ValueObjects\InvoiceChaseTimeline;
use App\Chasing\ValueObjects\InvoiceChaseTimelineSegment;
use App\Core\Database\TransactionManager;
use App\Sending\Models\ScheduledSend;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * Schedules actions according to an InvoiceDelivery's chase schedule.
 */
class InvoiceDeliveryProcessor
{
    /**
     * Map of 'role:date' string -> ScheduledSend instance used to determine
     * when a ScheduledSend needs to be skipped.
     *
     * @var array<string,ScheduledSend>
     */
    private array $replacements = [];
    private InvoiceChaseTimeline $timeline;

    public function __construct(private Connection $database, private TransactionManager $transaction)
    {
    }

    public function initialize(): void
    {
        $this->replacements = [];
    }

    /**
     * Whether or not chasing should be active.
     */
    private function shouldChase(InvoiceDelivery $delivery): bool
    {
        return !($delivery->disabled ||
            $delivery->invoice->paid ||
            $delivery->invoice->closed ||
            $delivery->invoice->voided);
    }

    /**
     * Processes an invoice delivery according to the outlined rules.
     */
    public function process(InvoiceDelivery $delivery): void
    {
        $this->transaction->perform(function () use ($delivery) {
            $this->timeline = (new InvoiceChaseTimelineBuilder())->build($delivery);

            // 1.
            // Marked as processed prior to processing to ensure
            // changes that occur while processing aren't missed.
            $delivery->processed = true;
            $delivery->saveOrFail();

            // 2.
            $this->deleteOrphanedSends($delivery);

            // 3.
            if ($this->shouldChase($delivery)) {
                foreach ($this->timeline as $segment) {
                    $this->processStep($delivery, $segment);
                }
            } else {
                $this->cancelUnsentSends($delivery);
            }
        });
    }

    //
    // Timeline Processing
    //

    /**
     * Creates, updates and removes ScheduledSends per chase step.
     */
    private function processStep(InvoiceDelivery $delivery, InvoiceChaseTimelineSegment $segment): void
    {
        $scheduledSends = ScheduledSend::where('invoice_id', $delivery->invoice_id)
            ->where('reference', InvoiceDelivery::getSendReference($delivery, $segment->getChaseStep()))
            ->sort('send_after ASC')
            ->all();

        // group by date and channel
        $groups = [];
        /** @var ScheduledSend $send */
        foreach ($scheduledSends as $send) {
            $group = $groups[$send->send_after] ?? [
                    'email' => null,
                    'sms' => null,
                    'letter' => null,
                ];
            $group[ScheduledSend::getChannelString($send->channel)] = $send;
            $groups[$send->send_after] = $group;
        }

        // sort the groups by date
        ksort($groups);

        // selected channels
        $chaseStep = $segment->getChaseStep();
        $chaseStepOptions = $chaseStep->getOptions();
        $emailSelected = $chaseStepOptions['email'] ?? false;
        $smsSelected = $chaseStepOptions['sms'] ?? false;
        $letterSelected = $chaseStepOptions['letter'] ?? false;

        // iterate groups: create, update, cancel scheduled sends
        $i = 0;
        $dates = $segment->getDates();
        while ($i < min(count($groups), count($dates))) {
            $newDate = $dates[$i];
            $group = $groups[array_keys($groups)[$i]];
            // check email
            $this->processSend($delivery, $chaseStep, 'email', $emailSelected, $newDate, $group['email']);
            // check sms
            $this->processSend($delivery, $chaseStep, 'sms', $smsSelected, $newDate, $group['sms']);
            // check letter
            $this->processSend($delivery, $chaseStep, 'letter', $letterSelected, $newDate, $group['letter']);
            ++$i;
        }

        // process the date differences
        if ($i < count($groups)) {
            // delete ScheduledSends for the dates that have been removed
            while ($i < count($groups)) {
                $group = $groups[array_keys($groups)[$i]];
                if ($group['email'] instanceof ScheduledSend) {
                    $group['email']->delete();
                }

                if ($group['sms'] instanceof ScheduledSend) {
                    $group['sms']->delete();
                }

                if ($group['letter'] instanceof ScheduledSend) {
                    $group['letter']->delete();
                }

                ++$i;
            }
        } elseif ($i < count($dates)) {
            // create ScheduledSends for the newly added dates
            $newDates = array_slice($dates, $i);
            foreach ($newDates as $newDate) {
                // check email
                $this->processSend($delivery, $chaseStep, 'email', $emailSelected, $newDate);
                // check sms
                $this->processSend($delivery, $chaseStep, 'sms', $smsSelected, $newDate);
                // check letter
                $this->processSend($delivery, $chaseStep, 'letter', $letterSelected, $newDate);
            }
        }
    }

    /**
     * Handles the CRUD operations of a ScheduledSend with respect to
     * an invoice chasing step.
     */
    private function processSend(InvoiceDelivery $delivery, InvoiceChaseStep $step, string $channel, bool $channelSelected, CarbonImmutable $date, ?ScheduledSend $send = null): void
    {
        if ($send instanceof ScheduledSend) {
            if ($channelSelected && !$send->attempted(false)) {
                // update
                // don't modify sends that have already been attempted
                $this->setScheduledSend($delivery, $step, $channel, $date, $send);
            } elseif (!$channelSelected) {
                // remove
                $send->delete();
            }
        } elseif ($channelSelected) {
            // create scheduled send
            $this->setScheduledSend($delivery, $step, $channel, $date);
        }
    }

    /**
     * Updates or creates a ScheduledSend instance with respect to
     * an invoice chasing step.
     */
    private function setScheduledSend(InvoiceDelivery $delivery, InvoiceChaseStep $step, string $channel, CarbonImmutable $date, ?ScheduledSend $send = null): void
    {
        // do not create backdated sends
        $now = CarbonImmutable::now();
        // Existing sends should still be updated if the date is less than now
        // because they're being updated to be canceled.
        if ($date->lessThan($now) && !($send instanceof ScheduledSend)) {
            return;
        }

        // build parameters
        $parameters = null;
        if (ScheduledSend::EMAIL_CHANNEL_STR === $channel) {
            if ($roleId = $step->getOptions()['role'] ?? null) {
                $parameters = [
                    'role' => $roleId,
                ];
            } elseif ($contacts = $this->buildEmailContacts($delivery)) {
                $parameters = [
                    'to' => $contacts,
                ];
            }
        }

        $send ??= new ScheduledSend();
        $send->invoice = $delivery->invoice;
        $send->parameters = $parameters;
        $send->setChannel($channel);
        $send->setSendAfter($date);
        $send->canceled = $date->lessThan($now);
        $send->reference = InvoiceDelivery::getSendReference($delivery, $step);

        $replacement = $this->findReplacement($send, $step, $date);
        $send->replacement = $replacement;
        $send->skipped = (bool) $replacement;

        $send->saveOrFail();
    }

    //
    // Mass database operations
    //

    /**
     * Deletes ScheduledSends that reference chase steps which have
     * been removed.
     */
    private function deleteOrphanedSends(InvoiceDelivery $delivery): void
    {
        // build list of references to scheduled sends which should not be deleted
        $refList = [];
        foreach ($this->timeline as $segment) {
            $refList[] = 'delivery:'.$delivery->id().':'.$segment->getChaseStep()->getId();
        }

        $deliveryReference = '%delivery:'.$delivery->id().':%';
        $query = $this->database->createQueryBuilder()
            ->delete('ScheduledSends')
            ->where('tenant_id = :tid')
            ->andWhere('invoice_id = :id')
            ->andWhere('reference LIKE :dref')
            ->setParameter('tid', (int) $delivery->tenant()->id())
            ->setParameter('id', (int) $delivery->invoice->id())
            ->setParameter('dref', $deliveryReference);
        if (count($refList) > 0) {
            $query = $query->andWhere('reference NOT IN (:refList)')
                ->setParameter('refList', $refList, Connection::PARAM_STR_ARRAY);
        }

        $query->executeStatement();
    }

    /**
     * Cancels all un-attempted sends which reference the InvoiceDelivery.
     */
    private function cancelUnsentSends(InvoiceDelivery $delivery): void
    {
        $deliveryReference = '%delivery:'.$delivery->id().':%';
        $this->database->createQueryBuilder()
            ->update('ScheduledSends')
            ->set('canceled', 'TRUE')
            ->where('tenant_id = :tid')
            ->andWhere('invoice_id = :id')
            ->andWhere('reference LIKE :dref')
            ->andWhere('sent = FALSE')
            ->andWhere('skipped = FALSE')
            ->andWhere('canceled = FALSE')
            ->andWhere('failed = FALSE')
            ->setParameter('tid', (int) $delivery->tenant()->id())
            ->setParameter('id', (int) $delivery->invoice->id())
            ->setParameter('dref', $deliveryReference)
            ->executeStatement();
    }

    //
    // Replacement Helpers
    //

    /**
     * Finds a replacement ScheduledSend for the one provided.
     */
    private function findReplacement(ScheduledSend $send, InvoiceChaseStep $step, CarbonImmutable $date): ?ScheduledSend
    {
        $role = $step->getOptions()['role'] ?? '';
        $invoiceId = (string) $send->invoice?->id();
        $id = $invoiceId.':'.$role.':'.$send->channel.':'.$date->toDateString();

        // check if there is a replacement
        if (isset($this->replacements[$id]) && $send->id != $this->replacements[$id]->id) {
            return $this->replacements[$id];
        }

        $this->replacements[$id] = $send;

        return null;
    }

    //
    // Contact Helpers
    //

    /**
     * Builds a list of email contacts for the scheduled send.
     *
     * @return string[][]
     */
    private function buildEmailContacts(InvoiceDelivery $delivery): array
    {
        return array_map(fn ($email) => [
            'email' => $email,
        ], $delivery->getEmails());
    }
}
