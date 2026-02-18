<?php

namespace App\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingStatistic;
use App\Chasing\Models\CompletedChasingStep;
use App\Chasing\Models\Task;
use App\Core\Orm\Event\AbstractEvent;
use Carbon\CarbonImmutable;

/**
 * Moves the chasing pointer
 * based on invoice activity.
 */
class ChasingInvoiceListener
{
    private static self $listener;

    private array $resetCheck = [];

    /**
     * Handles the model updating event.
     */
    public function onUpdating(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        $customer = $invoice->customer();
        if (!($customer instanceof Customer) || !$customer->chasingCadence()) {
            return;
        }

        // check if we need to potentially reset the
        // next chasing step for the customer to the
        // first step in the chasing cadence
        // Conditions for reset:
        // 1) Paying an invoice in full
        // 2) Paying an installment in full (balance changed)
        // 3) Closing an invoice
        // 4) Voiding an invoice
        // 5) Deleting an invoice

        // # 1 and # 2 are covered by checking the balance
        // # 5 is covered by the model.deleted event
        if (($invoice->balance != $invoice->ignoreUnsaved()->balance) ||
            ($invoice->closed && !$invoice->ignoreUnsaved()->closed) ||
            ($invoice->voided && !$invoice->ignoreUnsaved()->voided)) {
            $this->resetCheck[$invoice->id()] = true;
        }
    }

    /**
     * Handles the model updated event.
     */
    public function onUpdated(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        if (!isset($this->resetCheck[$invoice->id()])) {
            return;
        }

        unset($this->resetCheck[$invoice->id()]);

        $customer = $invoice->customer();
        if ($customer instanceof Customer && $this->shouldBeReset($customer, $invoice)) {
            $this->reset($customer);
        }
        $paid = !$invoice->voided && (!$invoice->closed || $invoice->paid);
        $this->updateChasingStatistics($invoice, $paid);
    }

    /**
     * Handles the model deleted event.
     */
    public function onDeleted(AbstractEvent $event): void
    {
        /** @var Invoice $invoice */
        $invoice = $event->getModel();

        $customer = $invoice->customer();
        if (!($customer instanceof Customer) || !$customer->chasingCadence()) {
            return;
        }

        // check if we need to potentially reset the
        // next chasing step for the customer to the
        // first step in the chasing cadence, because
        // deleting the invoice causes the balance to
        // be paid in full
        if ($this->shouldBeReset($customer, $invoice)) {
            $this->reset($customer);
        }
        $this->updateChasingStatistics($invoice, false);
    }

    private function updateChasingStatistics(Invoice $invoice, bool $responsible): void
    {
        $statistics = ChasingStatistic::where('invoice_id', $invoice->id)->where('paid IS NULL')->all();
        foreach ($statistics as $statistic) {
            $statistic->paid = CarbonImmutable::now()->toIso8601String();
            $statistic->payment_responsible = $responsible;
            $statistic->save();
        }
    }

    /**
     * Checks if a customer's chasing schedule needs to
     * be reset because the balance is now paid in full.
     */
    public function shouldBeReset(Customer $customer, Invoice $invoice): bool
    {
        $generator = new ChasingBalanceGenerator();
        $chasingBalance = $generator->generate($customer, $invoice->currency);

        return $chasingBalance->getBalance()->isZero();
    }

    private function reset(Customer $customer): void
    {
        // reset the customer to the first step in the cadence
        /** @var ChasingCadence $cadence */
        $cadence = $customer->chasingCadence();
        $customer->next_chase_step = (int) $cadence->getSteps()[0]->id();

        // do not proceed with resetting the customer if already on the first step
        if (!$customer->dirty('next_chase_step_id', true)) {
            return;
        }

        $customer->skipReconciliation();
        $customer->saveOrFail();

        // delete any incomplete collection tasks
        Task::getDriver()
            ->getConnection(null)
            ->executeStatement('DELETE FROM Tasks WHERE tenant_id=? AND customer_id=? AND complete=0', [$customer->tenant_id, $customer->id()]);

        // delete any completed chasing steps
        CompletedChasingStep::getDriver()
            ->getConnection(null)
            ->executeStatement('DELETE FROM CompletedChasingSteps WHERE tenant_id=? AND customer_id=?', [$customer->tenant_id, $customer->id()]);
    }

    /**
     * Installs the event listener on the Invoice model.
     */
    public static function listen(): void
    {
        if (!isset(self::$listener)) {
            self::$listener = new self();
        }

        // This step should execute after the invoice has been recalculated
        Invoice::updating([self::$listener, 'onUpdating'], -201);
        Invoice::updated([self::$listener, 'onUpdated']);
        Invoice::deleted([self::$listener, 'onDeleted']);
    }
}
