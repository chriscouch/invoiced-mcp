<?php

namespace App\Integrations\Stripe;

use App\CustomerPortal\Exceptions\PaymentLinkException;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use App\PaymentProcessing\Exceptions\TransactionStatusException;

class ReconcileStripePaymentFlow
{
    public function __construct(
        private readonly PaymentFlowReconcile $paymentFlowReconcile,
        private readonly StripeGateway $gateway,
    ) {
    }

    /**
     * @throws ChargeException
     * @throws FormException
     * @throws PaymentLinkException
     * @throws TransactionStatusException
     */
    public function reconcile(PaymentFlow $flow): void
    {
        if (!$flow->merchant_account) {
            return;
        }

        $data = $this->gateway->searchTransaction($flow->merchant_account, $flow->identifier);
        if (!$data) {
            $flow->status = PaymentFlowStatus::Canceled;
            $flow->save();

            return;
        }

        if (count($data) > 1) {
            return;
        }

        $data = PaymentFlowReconcileData::fromStripe($data[0]);

        // if initiated charge exists, we do not attempt to restore it
        // there is separate replay job
        // this saves sentry units
        if (InitiatedCharge::where('gateway', StripeGateway::ID)
            ->where("charge LIKE '%{$data->gatewayId}%'")
            ->oneOrNull()) {
            return;
        }

        $this->paymentFlowReconcile->doReconcile($flow, $data);
    }
}
