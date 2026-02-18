<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Invoice;
use InvalidArgumentException;

class RetrySchedule
{
    private array $schedule;

    /**
     * Creates a retry schedule. The schedule is expressed
     * as an array of discrete steps. Each step represents
     * the # of days after the past step when a retry should
     * be attempted.
     */
    public function __construct(private Invoice $invoice, array $schedule)
    {
        foreach ($schedule as &$step) {
            $step = round($step);
        }
        $this->schedule = $schedule;
    }

    /**
     * Gets the schedule.
     */
    public function getSchedule(): array
    {
        return $this->schedule;
    }

    /**
     * Calculates the next payment attempt.
     */
    public function next(): ?int
    {
        $invoice = $this->invoice;
        // since we index from 0
        // we should subtract 1
        $step = $invoice->getNextAttemptNumber() - 1;
        if ($step < 0) {
            throw new InvalidArgumentException('Invalid step: '.$step);
        }
        $lastAttempt = $invoice->getLastAttempt();
        if (isset($this->schedule[$step])) {
            $n = $this->schedule[$step];

            return max(strtotime('+1 day'), strtotime("+$n days", $lastAttempt));
        }

        // all attempts have been exhausted
        return null;
    }

    /**
     * Validates a retry schedule.
     */
    public static function validate(array $schedule): bool
    {
        // schedules are allowed a maximum of 4 failed retry attempts
        if (count($schedule) > 4) {
            return false;
        }

        // each step cannot be greater than 10 days from the previous
        foreach ($schedule as $step) {
            if ($step > 10 || $step < 1) {
                return false;
            }
        }

        return true;
    }
}
