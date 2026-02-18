<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Enums\ChargebackEvent;
use App\Integrations\Adyen\Operations\SaveAdyenDisputeFee;
use App\Integrations\Adyen\ValueObjects\AdyenChargebackWebhookEvent;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use App\PaymentProcessing\Reconciliation\DisputeReconciler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenChargebackSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly DisputeReconciler $disputeReconciler,
        private readonly SaveAdyenDisputeFee $disputeFeeSaveOperation,
        private readonly UpdateChargeStatus $updateChargeStatus,
    ) {
    }

    public function process(AdyenChargebackWebhookEvent $event): void
    {
        if (!$chargebackEvent = ChargebackEvent::tryFrom($event->data['eventCode'])) {
            return;
        }

        $reference = $event->data['originalReference'] ?? null;

        if ($reference) {
            /** @var ?Charge $charge */
            $charge = Charge::where('gateway_id', $reference)
                ->where('gateway', AdyenGateway::ID)
                ->oneOrNull();

            if ('bank_account' === $charge?->payment_source_type) {
                $this->updateChargeStatus->saveStatus($charge, Charge::FAILED, $event->data['reason'] ?? null);

                return;
            }
        }

        $amount = new Money($event->data['amount']['currency'], $event->data['amount']['value']);
        $parameters = [
            'charge_gateway_id' => $reference,
            'gateway_id' => $event->data['pspReference'],
            'gateway' => AdyenGateway::ID,
            'currency' => $event->data['amount']['currency'],
            'amount' => $amount->toDecimal(),
            'status' => $chargebackEvent->toDisputeStatus(),
            'reason' => $event->data['reason'] ?? null,
        ];

        $dispute = $this->disputeReconciler->reconcile($parameters);
        if (!$dispute) {
            return;
        }

        $this->disputeFeeSaveOperation->save($dispute, $chargebackEvent);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenChargebackWebhookEvent::class => 'process',
        ];
    }
}
