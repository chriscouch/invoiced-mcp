<?php

namespace App\Chasing\Legacy;

use App\Companies\Models\Company;

/**
 * An immutable chasing schedule object.
 * Chasing schedules are represented as an array of steps. Each step
 * normally represents the # of days relative to the due date.
 *
 * Special rules:
 * - First element can be "issued". This means the invoice will
 *   be chased as soon as it is issued.
 * - Last element can be a repeater in the form "~3"
 *
 * Example schedule:
 *   ["issued", -7, 0, 3, 10, "~3"].
 *
 * @deprecated Part of the legacy feature 'legacy_chasing'
 */
class ChaseSchedule
{
    /**
     * Builds the chasing schedule for a company.
     */
    public static function get(Company $company): self
    {
        return self::buildFromArray($company->accounts_receivable_settings->chase_schedule);
    }

    /**
     * Builds a chasing schedule from its array representation.
     * This method will sanitize the input schedule to fix
     * common errors like step duplication and misordering.
     */
    public static function buildFromArray(array $schedule): self
    {
        // convert to steps
        $steps = [];
        foreach ($schedule as $row) {
            if (is_object($row)) {
                $steps[] = new ChaseScheduleStep($row->step, $row->action);
            } elseif (is_array($row)) {
                $steps[] = ChaseScheduleStep::fromArray($row);
            } else {
                // legacy format
                if (is_numeric($row)) {
                    $row = (int) $row;
                }

                $steps[] = new ChaseScheduleStep($row);
            }
        }

        // remove duplicates
        $steps = array_unique($steps);

        // order schedule
        usort($steps, [self::class, 'compare']);

        return new self($steps);
    }

    /**
     * Validates a chasing schedule.
     */
    public static function validate(array $value): bool
    {
        return self::buildFromArray($value)->isValid();
    }

    /**
     * @param ChaseScheduleStep[] $steps
     */
    public function __construct(private array $steps)
    {
    }

    /**
     * Gets the steps in the schedule.
     *
     * @return ChaseScheduleStep[]
     */
    public function getSteps()
    {
        return $this->steps;
    }

    /**
     * Converts the schedule to an array representation.
     */
    public function toArray(bool $newFormat = false): array
    {
        $steps = [];
        foreach ($this->steps as $step) {
            if ($newFormat) {
                $steps[] = $step->toArray();
            } else {
                // legacy format
                $steps[] = $step->getStep();
            }
        }

        return $steps;
    }

    /**
     * Checks if the schedule is valid.
     */
    public function isValid(): bool
    {
        // validate each step
        $numRepeats = 0;
        foreach ($this->steps as $step) {
            // validate repeats
            $n = $step->getStep();
            if (!is_numeric($n)) {
                if (ChaseScheduleStep::STEP_ISSUED !== $n && ('~' != substr($n, 0, 1) || !ctype_digit(substr($n, 1)))) {
                    return false;
                }

                if (ChaseScheduleStep::STEP_ISSUED !== $n) {
                    ++$numRepeats;
                }
            }
        }

        // can only have 1 repeat
        if ($numRepeats > 1) {
            return false;
        }

        return true;
    }

    /**
     * Calculates the next chasing date in the schedule.
     *
     * @return array|null [next timestamp, next action]
     */
    public function next(int $date, ?int $dueDate, ?int $lastSent): ?array
    {
        // compute next from all non-repeat values
        $next = 0;
        foreach ($this->steps as $step) {
            // handle sending on issue
            $n = $step->getStep();
            if (ChaseScheduleStep::STEP_ISSUED === $n) {
                // do not chase if the invoice has already been sent
                if (!$lastSent) {
                    return [$date, $step->getAction()];
                }

                continue;
            }

            // the next steps all require a due date
            if (!$dueDate) {
                continue;
            }

            // handle a relative # of days, i.e. "-7"
            if (is_numeric($n)) {
                $next = $dueDate + $n * 86400;

                if ($next > $lastSent) {
                    return [$next, $step->getAction()];
                }
                // handle repeats, i.e. "~3"
            } else {
                // Treat schedules where the first element is a repeat
                // as if a previous component of '0'. This means
                // that repeating only schedules start N days after $dueDate
                if (0 == $next) {
                    $next = $dueDate;
                    $lastSent = max($dueDate, $lastSent);
                }

                // repeat N days from the previous schedule component
                // (or from $dueDate if repeat is only component)
                $n = (int) substr($n, 1);
                $nSecs = $n * 86400;
                // determine how many multiples in the future are needed
                $multiples = ceil(($lastSent - $next + 1) / $nSecs);

                $timestamp = $next + $multiples * $nSecs;

                return [$timestamp, $step->getAction()];
            }
        }

        return null;
    }

    /**
     * Compares 2 schedule components.
     *
     * @return int -1,0,1
     */
    public static function compare(ChaseScheduleStep $stepA, ChaseScheduleStep $stepB): int
    {
        $a = $stepA->getStep();
        $b = $stepB->getStep();
        $aIsIssued = ChaseScheduleStep::STEP_ISSUED === $a;
        $bIsIssued = ChaseScheduleStep::STEP_ISSUED === $b;

        if ($aIsIssued != $bIsIssued) {
            return ($aIsIssued) ? -1 : 1;
        }

        $aIsNumber = is_numeric($a);
        $bIsNumber = is_numeric($b);

        if ($aIsNumber != $bIsNumber) {
            return ($aIsNumber) ? -1 : 1;
        }

        return $a <=> $b;
    }
}
