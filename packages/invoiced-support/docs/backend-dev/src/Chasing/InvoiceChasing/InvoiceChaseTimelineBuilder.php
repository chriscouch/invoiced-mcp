<?php

namespace App\Chasing\InvoiceChasing;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Chasing\ValueObjects\InvoiceChaseTimeline;
use App\Chasing\ValueObjects\InvoiceChaseTimelineSegment;
use App\Sending\Models\ScheduledSend;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use SplPriorityQueue;
use SplQueue;

/**
 * Builder of InvoiceChaseTimelines.
 */
class InvoiceChaseTimelineBuilder
{
    private SplPriorityQueue $p_queue;
    private SplQueue $queue;

    public function __construct()
    {
        $this->p_queue = new SplPriorityQueue();
        $this->queue = new SplQueue();
    }

    /**
     * Builds an invoice chase timeline based on the provided set of steps.
     * O(nlogn) time, O(n) space where n is the length of the set of steps.
     *
     * NOTE: Some date values in the timeline are not static. Some steps should
     * not be backdated; particularly the "ON_ISSUE", and "REPEATER" steps.
     * In this case that they would be backdated, the date returned will be
     * based on the current time of the method call.
     *
     * SIGNIFICANCE: While these dates are used to schedule sends for Invoice Chasing,
     * they should not be used to display the date that the send is scheduled for in the
     * chasing state because they will not be the same as the previous dates used to create
     * the scheduled sends. See the NOTICE in InvoiceChaseStateCalculator for more detail
     * regarding which dates are displayed to the user.
     */
    public function build(InvoiceDelivery $delivery): InvoiceChaseTimeline
    {
        $delivery->tenant()->useTimezone();

        // get and sort the dates of non-repeating steps
        // by inserting them into a priority queue based
        // prioritized on date
        foreach ($delivery->getChaseSchedule() as $step) {
            if (InvoiceChasingCadence::REPEATER == $step->getTrigger()) {
                // process repeater steps last
                $this->queue->push($step);
                continue;
            }

            $segment = new InvoiceChaseTimelineSegment($step, [$this->getChaseDate($delivery, $step)]);
            $this->p_queue->insert($segment, $segment->getStartDate());
        }

        // get the dates of the repeating steps by using
        // the date on the top of the priority queue as
        // a start date (i.e repeaters should start after
        // the last non-repeating step). If the priority
        // queue is empty, or the date of the top value
        // is less than the greater of the issue/due date
        // of the invoice, then the greater of the issue/due
        // date is used as the start date.
        while (!$this->queue->isEmpty()) {
            /** @var InvoiceChaseStep $repeaterStep */
            $repeaterStep = $this->queue->dequeue();
            /** @var InvoiceChaseTimelineSegment|null $lastSegment */
            $lastSegment = !$this->p_queue->isEmpty() ? $this->p_queue->top() : null;

            $dates = [];
            $daysRepeating = (int) $repeaterStep->getOptions()['days'];
            $maxRepetitions = (int) $repeaterStep->getOptions()['repeats'];
            $hour = (int) $repeaterStep->getOptions()['hour'];

            // get all dates for the current repeating step
            $numRepeats = 0;
            $offset = $daysRepeating;
            $startDate = $this->getRepeaterStartDate($delivery, $lastSegment, $repeaterStep);
            while ($numRepeats < $maxRepetitions) {
                $dates[] = $this->setDateTime($startDate->addDays($offset), $hour);
                $offset += $daysRepeating;
                ++$numRepeats;
            }

            $segment = new InvoiceChaseTimelineSegment($repeaterStep, $dates);
            $this->p_queue->insert($segment, $segment->getStartDate());
        }

        // build timeline
        $segments = [];
        while (!$this->p_queue->isEmpty()) {
            /** @var InvoiceChaseTimelineSegment $segment */
            $segment = $this->p_queue->extract();
            $segments[] = $segment;
        }

        return new InvoiceChaseTimeline(array_reverse($segments));
    }

    /**
     * Returns a singular absolute date for an invoice chasing step. If the.
     *
     * NOTE: This value is not static for the "ON_ISSUE" trigger.
     *
     * @throws InvalidArgumentException if given a repeating step
     */
    private function getChaseDate(InvoiceDelivery $delivery, InvoiceChaseStep $step): CarbonImmutable
    {
        $invoice = $delivery->invoice;
        $trigger = $step->getTrigger();
        $options = $step->getOptions();
        $hour = (int) $options['hour'];
        $issueDate = CarbonImmutable::createFromTimestamp($invoice->date);
        $dueDate = CarbonImmutable::createFromTimestamp($invoice->due_date ?? $invoice->date);

        // issue date
        if (InvoiceChasingCadence::ON_ISSUE === $trigger) {
            // Look for concrete date based on scheduled send that's already been attempted
            // This is necessary so that the timeline dates don't change once the send on issue
            // is sent. We specifically only look for attempted sends because we still want to allow
            // updates to unsent sends.
            $send = ScheduledSend::where('invoice_id', $invoice->id())
                ->where('reference', InvoiceDelivery::getSendReference($delivery, $step))
                ->where('(sent = TRUE or failed = TRUE or skipped = TRUE)')
                ->first()[0] ?? null;
            if ($send instanceof ScheduledSend) {
                $sendAfter = $send->getSendAfter();
                if ($sendAfter instanceof CarbonImmutable) {
                    return $sendAfter;
                }
            }

            // use the greater of now, issue date
            $now = CarbonImmutable::now();
            $nowDateTime = $this->setDateTime($now, $now->hour + 1);
            $issueDateTime = $this->setDateTime($issueDate, $hour);

            return $issueDateTime->greaterThan($nowDateTime) ? $issueDateTime : $nowDateTime;
        }

        // before due
        if (InvoiceChasingCadence::BEFORE_DUE === $trigger) {
            // the earliest this step should be is the issue date
            $stepDate = $dueDate->addDays((int) -$options['days']);
            if ($stepDate->greaterThan($issueDate)) {
                return $this->setDateTime($stepDate, $hour);
            }

            return $this->setDateTime($issueDate, $hour);
        }

        // after due
        if (InvoiceChasingCadence::AFTER_DUE === $trigger) {
            $advance = (int) $options['days'];

            return $this->setDateTime($dueDate->addDays($advance), $hour);
        }

        // absolute date
        if (InvoiceChasingCadence::ABSOLUTE === $trigger) {
            return $this->setDateTime(CarbonImmutable::createFromTimeString($options['date']), $hour);
        }

        // after issue
        if (InvoiceChasingCadence::AFTER_ISSUE === $trigger) {
            $advance = (int) $options['days'];

            return $this->setDateTime($issueDate->addDays($advance), $hour);
        }

        throw new InvalidArgumentException("Chase step of type '$trigger' does not have a singular date.");
    }

    /**
     * Returns the start date of a repeater chase step.
     *
     * NOTE: The value returned is not static.
     */
    private function getRepeaterStartDate(InvoiceDelivery $delivery, ?InvoiceChaseTimelineSegment $lastSegment, InvoiceChaseStep $step): CarbonImmutable
    {
        $invoice = $delivery->invoice;
        // Look for concrete date based on scheduled send that's already been attempted
        // This is necessary so that the timeline dates don't change once the first repeater
        // is sent. We specifically only look for attempted sends because we still want to allow
        // updates to the start date if the repeater has not already started.
        $send = ScheduledSend::where('invoice_id', $invoice->id())
            ->where('reference', InvoiceDelivery::getSendReference($delivery, $step))
            ->where('(sent = TRUE or failed = TRUE or skipped = TRUE)')
            ->first()[0] ?? null;
        if ($send instanceof ScheduledSend) {
            $sendAfter = $send->getSendAfter();
            if ($sendAfter instanceof CarbonImmutable) {
                return $sendAfter->addDays(-(int) $step->getOptions()['days']);
            }
        }

        // Use the greatest of the last step, issue/due date, now.
        // I.e if the last step is backdated or there is no last step, the repeater should start
        // at the greater of now or the invoice issue/due date.
        $now = CarbonImmutable::now();
        $dueDate = CarbonImmutable::createFromTimestamp($invoice->due_date ?? $invoice->date);
        $lastSegmentDate = $lastSegment ? $lastSegment->getEndDate() : null;

        if (!($lastSegmentDate instanceof CarbonImmutable) || ($lastSegmentDate instanceof CarbonImmutable && $lastSegmentDate->lessThan($now))) {
            return $dueDate->lessThan($now) ? $now : $dueDate;
        }

        return $lastSegmentDate->lessThan($dueDate) ? $dueDate : $lastSegmentDate;
    }

    /**
     * Sets the time of a date to the given hour.
     */
    private function setDateTime(CarbonImmutable $date, int $hour): CarbonImmutable
    {
        return CarbonImmutable::createFromDate($date->year, $date->month, $date->day)
            ->setTime($hour, 0, 0, 0);
    }
}
