<?php

namespace App\PaymentProcessing\Reconciliation;

use App\Core\Database\TransactionManager;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Events\CompletedChargeEvent;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * This reconciles payment gateway charges with our
 * local database. It is also responsible for applying
 * charges to invoices.
 */
class ChargeReconciler implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private TransactionManager $transaction,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Reconciles a charge from a payment gateway by creating
     * a charge transaction.
     *
     * @throws ReconciliationException if unable to save the transaction
     *
     * @return Charge|null returns null if the charge already exists
     */
    public function reconcile(ChargeValueObject $charge, ChargeApplication $chargeApplication, ?PaymentFlow $paymentFlow, ?string $receiptEmail = null): ?Charge
    {
        try {
            $chargeModel = $this->transaction->perform(function () use ($charge, $chargeApplication, $receiptEmail, $paymentFlow) {
                // check if the charge has already been recorded
                $chargeAlreadyReconciled = $this->alreadyReconciled($charge);
                if (!empty($chargeAlreadyReconciled)) {
                    return $chargeAlreadyReconciled;
                }

                // create the charge object
                $chargeModel = $this->persistCharge($charge, $receiptEmail, $paymentFlow);

                // emit the completed charge event
                $event = new CompletedChargeEvent(
                    chargeValueObject: $charge,
                    chargeApplication: $chargeApplication,
                    paymentFlow: $paymentFlow,
                    receiptEmail: $receiptEmail,
                    charge: $chargeModel
                );
                $this->eventDispatcher->dispatch($event);

                // update the payment flow as the final step
                if ($paymentFlow) {
                    $this->updatePaymentFlow($charge, $paymentFlow);
                }

                return $chargeModel;
            });

            $this->statsd->increment('reconciliation.charge.succeeded');

            return $chargeModel;
        } catch (ReconciliationException $e) {
            $this->statsd->increment('reconciliation.charge.failed');

            throw $e;
        }
    }

    /**
     * Checks if the charge has already been saved in the database.
     */
    private function alreadyReconciled(ChargeValueObject $charge): ?Charge
    {
        return Charge::where('gateway', $charge->gateway)
            ->where('gateway_id', $charge->gatewayId)
            ->oneOrNull();
    }

    /**
     * Persists the charge to the database.
     *
     * @throws ReconciliationException
     */
    private function persistCharge(ChargeValueObject $charge, ?string $receiptEmail, ?PaymentFlow $paymentFlow): Charge
    {
        $chargeModel = new Charge();
        if ($charge->merchantAccount?->id) {
            $chargeModel->merchant_account = $charge->merchantAccount;
        }
        $chargeModel->payment_flow = $paymentFlow;
        $chargeModel->currency = $charge->amount->currency;
        $chargeModel->amount = $charge->amount->toDecimal();
        $chargeModel->customer = $charge->customer;
        $chargeModel->gateway = $charge->gateway;
        $chargeModel->gateway_id = $charge->gatewayId;
        $chargeModel->status = $charge->status;
        $chargeModel->receipt_email = $receiptEmail;
        $chargeModel->description = $charge->description;
        $chargeModel->last_status_check = time();
        if (Charge::FAILED == $charge->status) {
            $chargeModel->failure_message = $charge->failureReason;
        }
        if ($source = $charge->source) {
            $chargeModel->setPaymentSource($source);
        }

        if (!$chargeModel->save()) {
            throw new ReconciliationException('Could not save charge: '.$chargeModel->getErrors());
        }

        return $chargeModel;
    }

    private function updatePaymentFlow(ChargeValueObject $charge, PaymentFlow $paymentFlow): void
    {
        $paymentFlow->status = match ($charge->status) {
            ChargeValueObject::SUCCEEDED => PaymentFlowStatus::Succeeded,
            ChargeValueObject::FAILED => PaymentFlowStatus::Failed,
            default => PaymentFlowStatus::Processing,
        };
        $paymentFlow->save();
    }
}
