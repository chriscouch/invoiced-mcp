<?php

namespace App\Chasing\CustomerChasing\Actions;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\Task;
use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;

abstract class AbstractTaskAction extends AbstractAction
{
    abstract public function getTaskAction(): string;

    public function execute(ChasingEvent $event): ActionResult
    {
        $customer = $event->getCustomer();
        $step = $event->getStep();

        if ($this->alreadyHasTask($customer, $step)) {
            return new ActionResult(true);
        }

        $task = new Task();
        $task->customer = $customer;
        $task->name = $step->name;
        $task->action = $this->getTaskAction();
        $task->due_date = time();
        $task->user_id = $this->getUserForCustomer($customer, $step);
        $task->chase_step_id = (int) $step->id();
        $task->saveOrFail();

        return new ActionResult(true);
    }

    protected function getUserForCustomer(Customer $customer, ChasingCadenceStep $step): ?int
    {
        if ($userId = $step->assigned_user_id) {
            return $userId;
        }

        return $customer->owner_id;
    }

    protected function alreadyHasTask(Customer $customer, ChasingCadenceStep $step): bool
    {
        $query = Task::where('customer_id', $customer->id())
            ->where('action', $this->getTaskAction())
            ->where('complete', false)
            ->where('chase_step_id', $step);

        return $query->count() > 0;
    }
}
