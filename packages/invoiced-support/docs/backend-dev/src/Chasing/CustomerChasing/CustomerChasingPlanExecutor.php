<?php

namespace App\Chasing\CustomerChasing;

use App\Chasing\Enums\ChasingChannelEnum;
use App\Chasing\Enums\ChasingTypeEnum;
use App\Chasing\Libs\ChasingStatisticsRepository;
use App\Chasing\Models\ChasingStatistic;
use App\Chasing\Models\CompletedChasingStep;
use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;
use App\Companies\Models\Company;
use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * This class executes a given customer chasing plan. This includes
 * performing all the scheduled activities.
 */
class CustomerChasingPlanExecutor implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private readonly ActionCollection $actions, private readonly TransactionManager $transactionManager, private readonly ChasingStatisticsRepository $chasingStatisticsRepository, private Connection $connection)
    {
    }

    /**
     * Executes the generated action plan.
     *
     * @param ChasingEvent[] $actionPlan
     */
    public function execute(Company $tenant, iterable $actionPlan): void
    {
        /** @var ChasingStatistic[] $statistics */
        $statistics = [];
        foreach ($actionPlan as $event) {
            $result = $this->saveResult($event, $this->actions->execute($event));
            array_push($statistics, ...$result);
        }
        $this->chasingStatisticsRepository->massUpdate($tenant, $statistics);
    }

    /**
     * @throws Throwable
     *
     * @return ChasingStatistic[]
     */
    private function saveResult(ChasingEvent $chasingEvent, ActionResult $result): array
    {
        /*
         * @return ChasingStatistic[]
         */
        return $this->transactionManager->perform(function () use ($chasingEvent, $result): array {
            /** @var ChasingStatistic[] $statistics */
            $statistics = [];

            $customer = $chasingEvent->getCustomer();
            $step = $chasingEvent->getStep();

            $customerId = (int) $customer->id();
            $stepId = (int) $step->id();

            $completedStep = new CompletedChasingStep();
            $completedStep->successful = $result->isSuccessful();
            $completedStep->message = $result->getMessage();
            $completedStep->timestamp = time();
            $completedStep->customer_id = $customerId;
            $completedStep->cadence_id = $step->chasing_cadence_id;
            $completedStep->chase_step_id = $stepId;
            $completedStep->saveOrFail();

            $customer->next_chase_step_id = null;
            if ($nextStep = $chasingEvent->getNextStep()) {
                $customer->next_chase_step_id = (int) $nextStep->id();
            }
            $customer->skipReconciliation();
            $customer->saveOrFail();

            if ($result->isSuccessful()) {
                $this->statsd->increment('successful_chasing_action', 1.0, [
                    'chase_level' => 'customer',
                    'action' => $step->action,
                ]);

                $invoices = $chasingEvent->getInvoices();
                foreach ($invoices as $invoice) {
                    $attempts = (int) $this->connection->fetchOne('SELECT max(attempts) FROM ChasingStatistics WHERE invoice_id=?', [$invoice->id]);

                    $statistic = new ChasingStatistic();
                    $statistic->type = ChasingTypeEnum::Customer->value;
                    $statistic->attempts = $attempts + 1;
                    $statistic->customer_id = $customerId;
                    $statistic->cadence_id = $step->chasing_cadence_id;
                    $statistic->cadence_step_id = $step->id;
                    $statistic->invoice_id = $invoice->id;
                    $statistic->channel = ChasingChannelEnum::fromChasingCadenceStep($step)->value;
                    $statistic->date = CarbonImmutable::now()->toIso8601String();
                    $statistics[] = $statistic;
                }

                return $statistics;
            }
            $this->statsd->increment('failed_chasing_action', 1.0, [
                    'chase_level' => 'customer',
                    'action' => $step->action,
                ]);

            return [];
        });
    }
}
