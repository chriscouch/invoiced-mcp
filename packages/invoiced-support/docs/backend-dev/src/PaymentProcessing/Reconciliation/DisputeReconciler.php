<?php

namespace App\PaymentProcessing\Reconciliation;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingCreateEvent;
use App\Core\Database\TransactionManager;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class DisputeReconciler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TransactionManager $transactionManager,
        private EventSpool $eventSpool,
    ) {
    }

    public function reconcile(array $parameters): ?Dispute
    {
        return $this->transactionManager->perform(function () use ($parameters) {
            $dispute = Dispute::where('gateway_id', $parameters['gateway_id'])->oneOrNull();

            // Modify the status of an existing dispute
            if ($dispute) {
                if ($reason = $parameters['reason'] ?? null) {
                    $dispute->reason = $reason;
                    $dispute->saveOrFail();
                }

                $this->updateStatus($dispute, $parameters['status']);
            } else {
                $dispute = $this->createDispute($parameters);
            }

            return $dispute;
        });
    }

    public function updateStatus(Dispute $dispute, DisputeStatus $status): void
    {
        // lost can not be overridden
        if (DisputeStatus::Lost === $dispute->status ||
            // Won may only become lost
            (DisputeStatus::Won === $dispute->status && DisputeStatus::Lost !== $status) ||
            // Accepted may only become lost or Won
            (DisputeStatus::Accepted === $dispute->status && !in_array($status, [DisputeStatus::Lost, DisputeStatus::Won])) ||
            // Pending may only become lost or Won
            (DisputeStatus::Pending === $dispute->status && !in_array($status, [DisputeStatus::Lost, DisputeStatus::Won])) ||
            // Expired may not become Responded, Undefended, Unresponded
            (DisputeStatus::Expired === $dispute->status && in_array($status, [DisputeStatus::Responded, DisputeStatus::Undefended, DisputeStatus::Unresponded]))
        ) {
            $this->logger->info('Dispute already resolved or closed', ['dispute' => $dispute->id]);

            return;
        }

        $dispute->status = $status;
        $dispute->saveOrFail();
    }

    private function createDispute(array $parameters): ?Dispute
    {
        $charge = Charge::where('gateway_id', $parameters['charge_gateway_id'])->oneOrNull();
        unset($parameters['charge_gateway_id']);
        if (!$charge) {
            return null;
        }

        $dispute = new Dispute();
        $dispute->charge = $charge;
        foreach ($parameters as $key => $value) {
            $dispute->$key = $value;
        }
        $dispute->saveOrFail();

        $charge->disputed = true;
        $charge->saveOrFail();

        // Create an activity log event
        $pendingEvent = new PendingCreateEvent($dispute, EventType::DisputeCreated);
        $this->eventSpool->enqueue($pendingEvent);

        return $dispute;
    }
}
