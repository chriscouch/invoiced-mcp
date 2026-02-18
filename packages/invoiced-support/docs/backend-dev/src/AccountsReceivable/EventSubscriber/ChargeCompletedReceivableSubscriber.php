<?php

namespace App\AccountsReceivable\EventSubscriber;

use App\CashApplication\Models\Payment;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Events\CompletedChargeEvent;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ChargeCompletedReceivableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EventSpool $eventSpool,
        private NotificationSpool $notificationSpool,
        private EmailSpool $emailSpool,
    ) {
    }

    public function completeCharge(CompletedChargeEvent $event): void
    {
        $charge = $event->charge;
        $chargeValueObject = $event->chargeValueObject;
        $chargeApplication = $event->chargeApplication;

        // create the customer payment object
        $payment = $this->persistPayment($chargeApplication, $chargeValueObject, $charge);

        if ($payment) {
            if (Charge::SUCCEEDED == $charge->status) {
                $this->handleSucceededCharge($charge, $payment, $event);
            }

            $this->connectFlywirePayment($charge, $payment);
        }

        if (Charge::FAILED == $charge->status) {
            $this->handleFailedCharge($charge, $chargeApplication);
        }

        $this->createActivityLog($charge, $chargeApplication);
    }

    public static function getSubscribedEvents(): array
    {
        return [CompletedChargeEvent::class => 'completeCharge'];
    }

    /**
     * Creates a payment object and saves to the database.
     *
     * @throws ReconciliationException
     */
    public function persistPayment(ChargeApplication $chargeApplication, ChargeValueObject $charge, Charge $chargeModel): ?Payment
    {
        // Failed charges should not create a payment
        if (Charge::FAILED == $charge->status) {
            return null;
        }

        // If not found then create a new transaction
        $payment = new Payment();
        foreach ($chargeApplication->paymentValues as $k => $v) {
            $payment->$k = $v;
        }
        $payment->setCustomer($charge->customer);
        $payment->currency = $charge->amount->currency;
        $payment->date = $charge->timestamp;
        $payment->amount = $charge->amount->toDecimal();
        $payment->charge = $chargeModel;
        $payment->method = $charge->method;
        $payment->reference = $charge->gatewayId;
        $payment->source = $chargeApplication->getPaymentSource()->toString();

        // build payment application splits
        $payment->applied_to = array_map(fn ($item) => $item->build(), $chargeApplication->getItems());

        if (!$payment->save()) {
            throw new ReconciliationException($payment->getErrors());
        }

        // update the charge with the payment reference
        $chargeModel->payment = $payment;
        $chargeModel->save();

        return $payment;
    }

    private function handleSucceededCharge(Charge $charge, Payment $payment, CompletedChargeEvent $event): void
    {
        // NOTE: Email sending should never happen
        // inside a database transaction since
        // it could introduce a potentially long delay
        // and cause a lock wait timeout.

        $this->sendReceipt($payment, $event->receiptEmail);

        // record an internal notification
        if ($charge->customer_id && PaymentFlowSource::AutoPay === $event->chargeApplication->getPaymentSource()) {
            $this->notificationSpool->spool(NotificationEventType::AutoPaySucceeded, $charge->tenant_id, $charge->id, $charge->customer_id);
        }
    }

    /**
     * Sends a payment receipt (if enabled).
     */
    private function sendReceipt(Payment $payment, ?string $email): void
    {
        if (EmailTriggers::make($payment->tenant())->isEnabled('new_charge')) {
            $to = [];
            if ($email) {
                foreach (explode(',', $email) as $address) {
                    $to[] = ['email' => $address];
                }
            }

            $emailTemplate = EmailTemplate::make($payment->tenant_id, EmailTemplate::PAYMENT_RECEIPT);
            // If the receipt email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed.
            $this->emailSpool->spoolDocument($payment, $emailTemplate, $to, false);
        }
    }

    private function handleFailedCharge(Charge $charge, ChargeApplication $chargeApplication): void
    {
        // record an internal notification
        if ($charge->customer_id && PaymentFlowSource::AutoPay === $chargeApplication->getPaymentSource()) {
            $this->notificationSpool->spool(NotificationEventType::AutoPayFailed, $charge->tenant_id, $charge->id, $charge->customer_id);
        }
    }

    private function createActivityLog(Charge $charge, ChargeApplication $chargeApplication): void
    {
        $type = match ($charge->status) {
            Charge::PENDING => EventType::ChargePending,
            Charge::FAILED => EventType::ChargeFailed,
            default => EventType::ChargeSucceeded,
        };
        $associations = [];
        foreach ($chargeApplication->getDocuments() as $document) {
            $associations[] = [$document->object, $document->id()];
        }
        $event = new PendingEvent(
            object: $charge,
            type: $type,
            associations: $associations
        );
        $this->eventSpool->enqueue($event);
    }

    /**
     * Connects a Flywire payment to the A/R payment for scenarios where the
     * webhook might have created the Flywire payment before the payment form
     * was submitted.
     */
    private function connectFlywirePayment(Charge $charge, Payment $payment): void
    {
        if (FlywireGateway::ID != $charge->gateway) {
            return;
        }

        $flywirePayment = FlywirePayment::where('payment_id', $charge->gateway_id)->oneOrNull();
        if (!$flywirePayment) {
            return;
        }

        if (!$flywirePayment->ar_payment) {
            $flywirePayment->ar_payment = $payment;
            $flywirePayment->saveOrFail();
        }
    }
}
