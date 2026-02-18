<?php

namespace App\PaymentPlans\Libs;

use App\Companies\Models\Company;
use App\Core\I18n\MoneyFormatter;
use App\PaymentPlans\Exception\PaymentPlanCalculatorException;

/**
 * Calculates payment installment plans based on the given constraints.
 *
 * Possible payment plan scenarios (that we handle):
 * 1. Fixed installment amount and fixed spacing
 * 2. Fixed installment amount and fixed stop date
 * 3. Fixed # of installments and fixed spacing
 * 4. Fixed # of installments and fixed stop date
 * 5. Fixed spacing and fixed end date
 *
 * Whenever one of these combinations are presented we need
 * to be able to compute a valid installment schedule from the
 * given constraints.
 */
class PaymentPlanCalculator
{
    public function __construct(Company $company)
    {
        $company->useTimezone();
    }

    /**
     * Builds an installment schedule given some constraints.
     *
     * @throws PaymentPlanCalculatorException
     *
     * @return array calculated schedule
     */
    public function build(int $startDate, string $currency, float $total, array $constraints): array
    {
        $schedule = [];

        // 1. End Date
        $constraints['end_date'] = $this->calcEndDate($startDate, $constraints);

        // 2. # Installments
        $constraints['num_installments'] = $this->calcNumInstallments($startDate, $total, $constraints);
        $schedule = $this->applyNumInstallments($schedule, $constraints);

        // 3. Start Date
        $schedule = $this->applyStartDate($startDate, $schedule);

        // 4. Installment Spacing
        $constraints['installment_spacing'] = $this->calcInstallmentSpacing($startDate, $constraints);
        $schedule = $this->applyInstallmentSpacing($schedule, $constraints);

        // 5. Installment Amount
        $constraints['installment_amount'] = $this->calcInstallmentAmount($currency, $total, $constraints);
        $schedule = $this->applyInstallmentAmount($currency, $total, $schedule, $constraints);

        return $schedule;
    }

    /**
     * Checks if an installment schedule matches the given constraints.
     *
     * @throws PaymentPlanCalculatorException when the schedule cannot be verified against the given constraints
     */
    public function verify(string $currency, array $schedule, array $constraints): bool
    {
        // check that there is at least one installment
        if (0 === count($schedule)) {
            throw new PaymentPlanCalculatorException('The schedule does not have any installments.');
        }

        // verify the installment length
        if (isset($constraints['num_installments']) && $constraints['num_installments'] > 0 && count($schedule) != $constraints['num_installments']) {
            throw new PaymentPlanCalculatorException('The schedule does not have the required number of installments.');
        }

        // keep track of money amounts in cents because
        // floating point comparison in javascript is painful
        $formatter = MoneyFormatter::get();
        $total = 0;
        $amountConstraint = isset($constraints['installment_amount']) ?
            $formatter->normalizeToZeroDecimal($currency, $constraints['installment_amount']) :
            false;
        $totalConstraint = isset($constraints['total']) ?
            $formatter->normalizeToZeroDecimal($currency, $constraints['total']) :
            false;

        foreach ($schedule as $i => $installment) {
            $amount = $installment['amount'] ?? 0;
            $amount = $formatter->normalizeToZeroDecimal($currency, $amount);
            if ($amount <= 0) {
                throw new PaymentPlanCalculatorException('Installments can only have positive amounts.');
            }

            $total += $amount;

            // verify the installment amount
            // (except for the last installment)
            if ($i == count($schedule) - 1 && $i > 0) {
                continue;
            }

            if ($amountConstraint && $amount != $amountConstraint) {
                throw new PaymentPlanCalculatorException('The installment amount(s) did not match the given constraint.');
            }
        }

        // verify the installments add up to the balance
        if ($totalConstraint && $total != $totalConstraint) {
            throw new PaymentPlanCalculatorException('The installment amounts did not add up to the balance.');
        }

        // verify the start date
        $first = $schedule[0];
        if (isset($constraints['start_date']) && !$this->datesMatch($first['date'], $constraints['start_date'])) {
            throw new PaymentPlanCalculatorException('Start date does not match the given constraint.');
        }

        // verify the end date
        $last = $schedule[count($schedule) - 1];
        if (isset($constraints['end_date']) && !$this->datesMatch($last['date'], $constraints['end_date'])) {
            throw new PaymentPlanCalculatorException('End date does not match the given constraint.');
        }

        return true;
    }

    //
    // Helpers
    //

    /**
     * Checks if 2 dates match given UNIX timestamps.
     */
    public function datesMatch(int $a, int $b): bool
    {
        return date('Ymd', $a) == date('Ymd', $b);
    }

    /**
     * Returns the UNIX timestamp of 00:00 on the same day
     * as the given timestamp.
     */
    public function startOfDay(int $t): int
    {
        return (int) mktime(0, 0, 0, (int) date('n', $t), (int) date('j', $t), (int) date('Y', $t));
    }

    //
    // Calculation Steps
    //

    private function calcEndDate(int $startDate, array $constraints): ?int
    {
        // use given value
        if (isset($constraints['end_date'])) {
            return $constraints['end_date'];
        }

        // calculate the end date from start date, spacing, and # installments
        if (isset($constraints['installment_spacing']) && isset($constraints['num_installments'])) {
            $end = $startDate;
            for ($i = 1; $i < $constraints['num_installments']; ++$i) {
                $end = (int) strtotime('+'.$constraints['installment_spacing'], $end);
            }

            return $end;
        }

        // if we cannot calculate the end date, then that's ok
        return null;
    }

    private function calcNumInstallments(int $startDate, float $total, array $constraints): int
    {
        $n = null;

        // use given constraint
        if (isset($constraints['num_installments']) && $constraints['num_installments'] > 0) {
            $n = $constraints['num_installments'];
        }

        // calculate from start date, end date, and spacing
        if (!$n && array_value($constraints, 'end_date') && isset($constraints['installment_spacing'])) {
            // compute diff in ms
            $diff = $constraints['end_date'] - $startDate;
            $spacing = strtotime('+'.$constraints['installment_spacing'], 0);
            $n = ceil($diff / $spacing);
        }

        // calculate from balance and installment amount
        if (!$n && isset($constraints['installment_amount'])) {
            $n = ceil($total / $constraints['installment_amount']);
        }

        // we have incomplete information
        if (null === $n) {
            throw new PaymentPlanCalculatorException('Incomplete information. Please add more constraints.');
        }

        // verify the computed amount
        $n = (int) $n;
        if ($n < 1) {
            throw new PaymentPlanCalculatorException('Does not produce at least 1 installment.');
        }

        return $n;
    }

    private function applyNumInstallments(array $schedule, array $constraints): array
    {
        // create N blank installments
        for ($i = 0; $i < $constraints['num_installments']; ++$i) {
            $schedule[] = [];
        }

        return $schedule;
    }

    private function applyStartDate(int $startDate, array $schedule): array
    {
        // set the date of the first installment
        $schedule[0]['date'] = $this->startOfDay($startDate);

        return $schedule;
    }

    private function calcInstallmentSpacing(int $startDate, array $constraints): string
    {
        $spacing = null;

        // use the given constraint
        if (isset($constraints['installment_spacing'])) {
            $spacing = $constraints['installment_spacing'];
        }

        // calculate from the start date, end date, and # installments
        if (!$spacing && isset($constraints['end_date']) && isset($constraints['num_installments'])) {
            // compute diff (in days)
            $diff = round(($constraints['end_date'] - $startDate) / ($constraints['num_installments'] - 1) / 86400);
            // convert diff to date interval object
            $spacing = "$diff days";
        }

        // we have incomplete information
        if (null === $spacing) {
            throw new PaymentPlanCalculatorException('Incomplete information. Please add more constraints.');
        }

        // verify the computed amount (>= 1 day)
        if (strtotime("+$spacing", 0) < 86400) {
            throw new PaymentPlanCalculatorException('Time between installments must be at least 1 day');
        }

        return $spacing;
    }

    private function applyInstallmentSpacing(array $schedule, array $constraints): array
    {
        $curr = $schedule[0]['date'];

        // set the dates of the remaining installments
        for ($i = 1; $i < count($schedule); ++$i) {
            $curr = (int) strtotime('+'.$constraints['installment_spacing'], $curr);

            // if this is the last installment then make sure it
            // matches the end date, when given
            if ($i == count($schedule) - 1 && $constraints['end_date']) {
                $schedule[$i]['date'] = $this->startOfDay($constraints['end_date']);

                break;
            }

            $schedule[$i]['date'] = $this->startOfDay($curr);
        }

        return $schedule;
    }

    private function calcInstallmentAmount(string $currency, float $total, array $constraints): float
    {
        $amount = null;

        // use given constraint
        if (isset($constraints['installment_amount']) && $constraints['installment_amount'] > 0) {
            $amount = $constraints['installment_amount'];
        }

        // calculate from balance and # installments
        if (!$amount && isset($constraints['num_installments'])) {
            $amount = $total / $constraints['num_installments'];
        }

        // we have incomplete information
        if (null === $amount) {
            throw new PaymentPlanCalculatorException('Incomplete information. Please add more constraints.');
        }

        // verify the computed amount
        $formatter = MoneyFormatter::get();
        $amount = $formatter->round($currency, $amount);
        if ($amount <= 0) {
            throw new PaymentPlanCalculatorException('The installment amount must be a positive number.');
        }

        return $amount;
    }

    private function applyInstallmentAmount(string $currency, float $total, array $schedule, array $constraints): array
    {
        // set the amount of each installment
        $remaining = $total;
        $formatter = MoneyFormatter::get();
        foreach ($schedule as $i => &$installment) {
            // the last installment is just the amount remaining
            if ($i == count($schedule) - 1) {
                $installment['amount'] = $formatter->round($currency, $remaining);

                break;
            }

            $installment['amount'] = $constraints['installment_amount'];
            $remaining -= $installment['amount'];
        }

        return $schedule;
    }
}
