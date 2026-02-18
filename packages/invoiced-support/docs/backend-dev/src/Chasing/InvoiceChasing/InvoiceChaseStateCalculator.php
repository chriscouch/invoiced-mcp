<?php

namespace App\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\ValueObjects\InvoiceChaseTimelineSegment;
use App\Sending\Models\ScheduledSend;
use Carbon\CarbonImmutable;

/**
 * State calculator for an invoice chase schedule. Aggregates invoice chase steps
 * w/ their associated ScheduledSends or mocks them if not yet processed.
 */
class InvoiceChaseStateCalculator
{
    /**
     * Attaches associated scheduled sends to each step in the schedule.
     */
    public static function getState(InvoiceDelivery $delivery): array
    {
        $data = [];
        $timeline = (new InvoiceChaseTimelineBuilder())->build($delivery);
        foreach ($timeline as $segment) {
            $data[] = self::buildStepState($delivery, $segment);
        }

        return $data;
    }

    /**
     * Builds the state for a single step in the chase schedule.
     */
    private static function buildStepState(InvoiceDelivery $delivery, InvoiceChaseTimelineSegment $segment): array
    {
        // get scheduled sends for the given step
        $scheduledSends = ScheduledSend::where('invoice_id', $delivery->invoice->id())
            ->where('reference', InvoiceDelivery::getSendReference($delivery, $segment->getChaseStep()))
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

        // build state
        $now = CarbonImmutable::now();
        $sendDetails = [];
        $dates = $segment->getDates();
        $sendCount = count($scheduledSends);
        $dateCount = count($dates);
        $minCount = min($sendCount, $dateCount);

        $step = $segment->getChaseStep();
        $i = 0;
        while ($i < $minCount) {
            $group = $groups[array_keys($groups)[$i]];
            $date = $dates[$i];

            // The options are checked because we shouldn't return scheduled sends
            // which will be canceled after processing
            /** @var ScheduledSend|null $emailSend */
            $emailSend = $step->getOptions()['email'] ? $group['email'] : null;
            /** @var ScheduledSend|null $smsSend */
            $smsSend = $step->getOptions()['sms'] ? $group['sms'] : null;
            /** @var ScheduledSend|null $letterSend */
            $letterSend = $step->getOptions()['letter'] ? $group['letter'] : null;

            // state date
            // NOTICE:
            // The date is taken from the scheduled sends if the delivery is processed or a send was already attempted.
            // Otherwise the date from the timeline builder is used. The reason is that the timeline builder's
            // date is only used by the InvoiceDeliveryProcessor if the send is unprocessed AND un-attempted.
            $singleSend = $emailSend ?? $smsSend ?? $letterSend;
            if ($delivery->processed || ($singleSend instanceof ScheduledSend && $singleSend->attempted(false))) {
                $stateDate = CarbonImmutable::createFromTimeString(array_keys($groups)[$i]);
            } else {
                $stateDate = $date;
            }

            // state skipped
            $skipped = $stateDate->lessThan($now) && $singleSend instanceof ScheduledSend &&
                (($delivery->processed && $singleSend->canceled) ||
                    (!$delivery->processed && !$singleSend->attempted(false)));

            // state variables
            $emailSend = $emailSend ? $emailSend->replacement ?? $emailSend : null;
            $smsSend = $smsSend ? $smsSend->replacement ?? $smsSend : null;
            $letterSend = $letterSend ? $letterSend->replacement ?? $letterSend : null;

            $failures = [
                'email' => $emailSend ? $emailSend->failure_detail : null,
                'sms' => $smsSend ? $smsSend->failure_detail : null,
                'letter' => $letterSend ? $letterSend->failure_detail : null,
            ];
            $sent = [
                'email' => $emailSend ? $emailSend->sent : false,
                'sms' => $smsSend ? $smsSend->sent : false,
                'letter' => $letterSend ? $letterSend->sent : false,
            ];

            $sendDetails[] = [
                'date' => $stateDate->toIso8601String(),
                'skipped' => $skipped,
                'failures' => $failures,
                'sent' => $sent,
            ];

            ++$i;
        }

        // build unprocessed states
        while ($i < $dateCount) {
            $date = $dates[$i];
            $sendDetails[] = [
                'date' => $date->toIso8601String(),
                'skipped' => $date->lessThan($now),
                'failures' => [
                    'email' => null,
                    'sms' => null,
                    'letter' => null,
                ],
                'sent' => [
                    'email' => false,
                    'sms' => false,
                    'letter' => false,
                ],
            ];
            ++$i;
        }

        $stepDetail = $segment->getChaseStep()->toArray();
        $stepDetail['state'] = $sendDetails;

        return $stepDetail;
    }
}
