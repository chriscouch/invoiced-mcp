<?php

namespace App\PaymentProcessing\Reconciliation;

use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingCreateEvent;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;

/**
 * This reconciles refunds from the payment gateway
 * with our local database.
 */
class RefundReconciler implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private EventSpool $eventSpool, private EmailSpool $emailSpool, private TransactionManager $transaction)
    {
    }

    /**
     * Reconciles a refund from a payment gateway by creating
     * a refund object.
     *
     * @param Charge $charge original charge being refunded
     *
     * @throws ReconciliationException if unable to save the transaction
     *
     * @return Refund|null returns null if the refund already exists
     */
    public function reconcile(RefundValueObject $refund, Charge $charge): ?Refund
    {
        try {
            $refundModel = $this->transaction->perform(function () use ($refund, $charge) {
                // ensure the refund has not already been recorded
                if ($this->alreadyReconciled($refund)) {
                    return null;
                }

                // save the refund to the database
                // create the refund object
                $refundModel = $this->persistRefund($refund, $charge);

                // update the charge refunded amount
                if (RefundValueObject::FAILED != $refund->status) {
                    $newRefundedAmount = $charge->getAmountRefunded()->add($refund->amount);
                    $charge->amount_refunded = $newRefundedAmount->toDecimal();
                    $charge->refunded = $newRefundedAmount->greaterThanOrEqual($charge->getAmount());
                    if (!$charge->save()) {
                        throw new ReconciliationException('Could not save charge: '.$charge->getErrors());
                    }
                }

                return $refundModel;
            });
        } catch (ReconciliationException $e) {
            $this->statsd->increment('reconciliation.refund.failed');

            throw $e;
        }

        // perform post-reconciliation activities before returning the result
        if ($refundModel) {
            $this->afterReconciliation($refundModel);
        }

        return $refundModel;
    }

    /**
     * Checks if the refund has already been recorded.
     */
    private function alreadyReconciled(RefundValueObject $refund): bool
    {
        return Refund::where('gateway', $refund->gateway)
            ->where('gateway_id', $refund->gatewayId)
            ->count() > 0;
    }

    /**
     * Persists the refund to the database.
     *
     * @throws ReconciliationException
     */
    private function persistRefund(RefundValueObject $refund, Charge $charge): Refund
    {
        $refundModel = new Refund();
        $refundModel->charge = $charge;
        $refundModel->gateway = $refund->gateway;
        $refundModel->gateway_id = (string) $refund->gatewayId;
        $refundModel->currency = $refund->amount->currency;
        $refundModel->amount = $refund->amount->toDecimal();
        $refundModel->status = $refund->status;
        if (RefundValueObject::FAILED == $refund->status) {
            $refundModel->failure_message = $refund->message;
        }

        if (!$refundModel->save()) {
            throw new ReconciliationException('Could not save refund: '.$refundModel->getErrors());
        }

        return $refundModel;
    }

    private function afterReconciliation(Refund $refund): void
    {
        $this->statsd->increment('reconciliation.refund.succeeded');

        $pendingEvent = new PendingCreateEvent($refund, EventType::RefundCreated);
        $this->eventSpool->enqueue($pendingEvent);

        // NOTE: Email sending should never happen
        // inside of a database transaction since
        // it could introduce a potentially long delay
        // and cause a lock wait timeout.
        $this->sendRefund($refund);
    }

    /**
     * Sends a refund receipt (if enabled).
     */
    private function sendRefund(Refund $refund): void
    {
        if (EmailTriggers::make($refund->tenant())->isEnabled('new_refund')) {
            $emailTemplate = EmailTemplate::make($refund->tenant_id, EmailTemplate::REFUND);
            // If the refund email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed.
            $this->emailSpool->spoolDocument($refund, $emailTemplate, [], false);
        }
    }
}
