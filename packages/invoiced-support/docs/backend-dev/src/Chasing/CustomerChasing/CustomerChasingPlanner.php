<?php

namespace App\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\ValueObjects\ChasingBalance;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Iterator;
use Generator;

/**
 * This class is responsible for generating a list of
 * activities that should be executed within a chasing run.
 */
class CustomerChasingPlanner
{
    public function __construct(private ActionCollection $actions, private ChasingBalanceGenerator $balanceGenerator)
    {
    }

    /**
     * Builds an action plan of the steps to be executed
     * within this run.
     *
     * @return Generator<ChasingEvent>
     */
    public function plan(ChasingCadence $cadence): Generator
    {
        // get list of cadence steps in descending order
        $steps = array_reverse($cadence->getSteps());

        foreach ($this->getCustomers($cadence) as $customer) {
            $nextStep = null;
            $lastCheckedStep = null;
            $stopAfterStep = $customer->next_chase_step;
            $currency = $customer->calculatePrimaryCurrency();

            // This keeps track of action types (i.e. email, sms, mail) that have
            // already been executed for the current customer.
            $alreadyExecutedActions = [];

            foreach ($steps as $step) {
                // Once we have reached the next scheduled chasing step for the customer
                // then we do not proceed further because those steps have already been
                // executed, or else the user has intentionally injected the customer
                // into this starting point in the cadence. This check must happen
                // IMMEDIATELY AFTER the loop iteration in which the step was seen.
                if ($stopAfterStep == $lastCheckedStep) {
                    break;
                }

                $lastCheckedStep = $step->id;

                // Certain action types can only be executed once per run,
                // i.e. the email action type
                $actionType = $step->action;
                if (isset($alreadyExecutedActions[$actionType]) && $this->actions->getForType($actionType)->limitOncePerRun()) {
                    continue;
                }

                $chasingBalance = $this->balanceGenerator->generate($customer, $currency);
                if (!$this->stepShouldRun($cadence, $chasingBalance, $step)) {
                    $nextStep = $step;

                    continue;
                }

                // Mark the action type as executed for this customer.
                $alreadyExecutedActions[$actionType] = true;

                yield ChasingEvent::fromChasingBalance($chasingBalance, $step, $nextStep);
            }
        }
    }

    /**
     * Gets customers that have a step in this cadence as the next step
     * and also have chasing enabled. Within this function we do not check
     * to see if the next step needs to be executed within this run. This
     * check happens later.
     *
     * @return Iterator<Customer>
     */
    public function getCustomers(ChasingCadence $cadence): Iterator
    {
        return Customer::where('chasing_cadence_id', $cadence)
            ->where('next_chase_step_id', null, '<>')
            ->where('chase', true)
            ->all();
    }

    /**
     * Checks if a chasing step should run.
     */
    public function stepShouldRun(ChasingCadence $cadence, ChasingBalance $chasingBalance, ChasingCadenceStep $step): bool
    {
        // only chase customers with an outstanding balance
        $accountBalanceAmount = $chasingBalance->getBalance();
        if ($accountBalanceAmount->isZero()) {
            return false;
        }

        // check if the balance meets the minimum threshold
        if ($cadence->min_balance > 0) {
            $minBalance = Money::fromDecimal($accountBalanceAmount->currency, $cadence->min_balance);
            if ($accountBalanceAmount->lessThan($minBalance)) {
                return false;
            }
        }

        // check if the age meets the minimum threshold
        $schedule = $step->schedule;
        $parts = explode(':', $schedule);
        if (ChasingCadenceStep::SCHEDULE_AGE == $parts[0]) {
            $minimumAge = (int) $parts[1];

            return $chasingBalance->getAge() >= $minimumAge;
        }

        // check if the past due age meets the minimum threshold
        if (ChasingCadenceStep::SCHEDULE_PAST_DUE_AGE == $parts[0] && $chasingBalance->isPastDue()) {
            $pastDueAge = $chasingBalance->getPastDueAge();
            $minimumPastDueAge = (int) $parts[1];

            return $pastDueAge >= $minimumPastDueAge;
        }

        return false;
    }
}
