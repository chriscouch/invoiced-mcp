<?php

namespace App\Integrations\Adyen\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\CashApplication\Models\Payment;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Libs\AdyenPaymentResultLock;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Exceptions\ChargeDeclinedException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;
use Throwable;

class SaveAdyenPayment implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly PaymentFlowReconcile $paymentFlowReconcile,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function tryReconcile(string $gatewayId, string $merchantReference, ?Money $amount = null): ?Charge
    {
        $charge = Charge::where('gateway_id', $gatewayId)
            ->where('gateway', AdyenGateway::ID)
            ->oneOrNull();
        if ($charge) {
            return $charge;
        }

        /** @var ?PaymentFlow $flow */
        $flow = PaymentFlow::where('identifier', $merchantReference)
            ->oneOrNull();
        if (!$flow) {
            $this->logger->error("Callback for payment received, but no flow found for reference: {$merchantReference}");

            return null;
        }

        // same flow may produce duplicate payment results
        /** @var AdyenPaymentResult[] $results */
        $results = AdyenPaymentResult::where('reference', $merchantReference)
            ->execute();

        if (!$results) {
            throw new AdyenReconciliationException('No payment result found for this flow.', $merchantReference);
        }

        foreach ($results as $result) {
            $data = json_decode($result->result, true);
            if ($data['pspReference'] !== $gatewayId) {
                continue;
            }

            $payment = $this->reconcileAdyenResult($flow, $result, $data, $amount);

            return $payment?->charge;
        }

        return null;
    }

    public function reconcileAdyenResult(PaymentFlow $flow, AdyenPaymentResult $result, array $data, ?Money $amount): ?Payment
    {
        $lock = new AdyenPaymentResultLock($data['pspReference'], $this->lockFactory);
        if (!$lock->acquire(AdyenPaymentResultLock::WRITE_ADYEN_TTL)) {
            throw new AdyenReconciliationException('Payment lock present for the reference.', $flow->identifier);
        }

        try {
            return $this->paymentFlowReconcile->reconcile($flow, PaymentFlowReconcileData::fromAdyenResult($result, $amount));
        } catch (ReconciliationException) {
            //do nothing
        } catch (Throwable $e) {
            $this->logger->error("Unexpected error processing payment flow: {$flow->identifier}", [
                'exception' => $e,
            ]);
        } finally {
            $lock->release();
        }

        return null;
    }

    public function reconcileFailedCharge(PaymentFlow $flow, array $result): void
    {
        //record failed charge
        if (PaymentFlowStatus::Failed !== $flow->status) {
            return;
        }

        $data = new PaymentFlowReconcileData(
            gateway: AdyenGateway::ID,
            status: ChargeValueObject::FAILED,
            gatewayId: $result['pspReference'],
            amount: $flow->getAmount(),
            brand: $result['paymentMethod']['brand'] ?? '',
            funding: 'unknown',
            last4: $result['additionalData']['cardSummary'] ?? '0000',
            expiry: $result['additionalData']['expiryDate'] ?? '',
            failureReason: $result['refusalReason'] ?? null,
            country: $result['additionalData']['cardIssuingCountry'] ?? null,
        );

        try {
            $this->paymentFlowReconcile->reconcile($flow, $data);
        } catch (ChargeDeclinedException) {
            //do nothing
        }
    }
}
