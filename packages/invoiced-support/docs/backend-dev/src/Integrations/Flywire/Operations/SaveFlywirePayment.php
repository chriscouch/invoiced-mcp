<?php

namespace App\Integrations\Flywire\Operations;

use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SaveFlywirePayment implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly FlywirePrivateClient $client,
        private readonly UpdateChargeStatus $updateChargeStatus,
        private readonly PaymentFlowReconcile $paymentFlowReconcile,
    ) {
    }

    /**
     * Syncs a single Payment from Flywire to our database.
     *
     * @throws IntegrationApiException
     */
    public function sync(string $paymentId, MerchantAccount $merchantAccount, bool $forceUpdate = false): void
    {
        // Retrieve the latest version of the Payment from the Flywire API.
        // Even if we get the data from a list API call, the status can
        // be incorrect due to latency. It is best to always fetch the
        // payment details because this shows real-time information.
        $data = $this->client->getPayment($paymentId);

        // Check for an existing payment model, and create one if needed
        $flywirePayment = FlywirePayment::where('payment_id', $paymentId)
            ->oneOrNull();
        if (!$flywirePayment) {
            $flywirePayment = new FlywirePayment();
            $flywirePayment->merchant_account = $merchantAccount;
        }

        $flow = null;
        foreach ($data['recipient']['fields'] ?? [] as $field) {
            if ($field['id'] === 'invoiced_ref') {
                $flow = $field['value'] ?? null;

                break;
            }
        }
        $flywirePayment->reference = $flow;

        $charge = Charge::where('gateway_id', $paymentId)
            ->where('gateway', FlywireGateway::ID)
            ->oneOrNull();

        // Update the Flywire payment record
        $this->savePayment($flywirePayment, $data, $charge, $forceUpdate);

        // Update the charge status
        if ($charge) {
            $this->updateChargeStatus($charge, $data);
        }
    }

    private function getSurchargePercentage(array $paymentData): float
    {
        $surchargePercentage = 0.00;

        if (!empty($paymentData['extra_fees'])) { // this is an array
            foreach ($paymentData['extra_fees'] as $fee) {
                if (!empty($fee['category']) && $fee['category'] === 'surcharge' && !empty($fee['amount_percentage'])) {
                    $surchargePercentage = $fee['amount_percentage'] * 100; // this should be like 0.03 * 100
                }
            }
        }

        return $surchargePercentage;
    }

    private function savePayment(FlywirePayment $flywirePayment, array $data, ?Charge $charge, bool $forceUpdate): void
    {
        $status = FlywirePaymentStatus::fromString($data['status']);

        $offerType = $data['offer']['type'] ?? null;
        $paymentId = $data['id'];
        // Attempt to link the Flywire payment to an Invoiced payment
        $hasChange = $forceUpdate;
        if (!$flywirePayment->ar_payment) {
            if ($charge) {
                $flywirePayment->ar_payment = $charge->payment;
                $hasChange = true;
            } elseif (in_array($status, [FlywirePaymentStatus::Guaranteed, FlywirePaymentStatus::Delivered])) {
                $payment = Payment::where('reference', $paymentId)
                    ->where('method', PaymentMethodType::BankTransfer->toString())
                    ->oneOrNull();

                if (!$payment) {
                    /** @var ?PaymentFlow $flow */
                    $flow = PaymentFlow::where('identifier', $flywirePayment->reference)->oneOrNull();
                    if ($flow) {
                        if (!$flow->merchant_account) {
                            $flow->merchant_account = $flywirePayment->merchant_account;
                            $flow->gateway = FlywireGateway::ID;
                            $flow->save();
                        }

                        $flowData = PaymentFlowReconcileData::fromFlywire($data);
                        $method = 'bank_transfer' === $offerType ? PaymentMethod::ACH : PaymentMethod::CREDIT_CARD;
                        if (!$payment = $this->paymentFlowReconcile->reconcile($flow, $flowData, $method)) {
                            if (PaymentMethod::ACH !== $method) {
                                return;
                            }
                            if (!$payment = $this->saveBankAccountPayment($paymentId, $data, $flowData->amount)) {
                                return;
                            }
                        }
                    }
                }

                $flywirePayment->ar_payment = $payment;
                $hasChange = true;
            }
        }

        // Do not sync payment if the status already matches our database,
        // and we are not associating an Invoiced payment
        if (!$flywirePayment->persisted() && $flywirePayment->status == $status && !$hasChange) {
            return;
        }

        $flywirePayment->payment_id = $paymentId;
        $flywirePayment->recipient_id = $data['recipient']['id'];
        $flywirePayment->initiated_at = new CarbonImmutable($data['created_at']);
        $flywirePayment->setAmountTo(new Money($data['purchase']['currency']['code'] ?? 'USD', $data['purchase']['value'] ?? 0));
        $flywirePayment->setAmountFrom(new Money($data['price']['currency']['code'] ?? 'USD', $data['price']['value'] ?? 0));
        $flywirePayment->surcharge_percentage = $this->getSurchargePercentage($data);
        $flywirePayment->status = $status;
        $flywirePayment->expiration_date = isset($data['due_at']) ? new CarbonImmutable($data['due_at']) : null;
        $flywirePayment->payment_method_type = $offerType;

        if ($data['charge_events']) {
            $chargeEvent = $data['charge_events'][0];
            if ($details = $chargeEvent['payment_method_details']) {
                $flywirePayment->payment_method_brand = $details['brand'] ?? null;
                $flywirePayment->payment_method_card_classification = $details['card_classification'] ?? null;
                $flywirePayment->payment_method_card_expiration = isset($details['expiration_month']) && $details['expiration_month'] ? $details['expiration_month'].'/'.$details['expiration_year'] : null;
                $flywirePayment->payment_method_last4 = $details['last_four_digits'] ?? null;
            }
        }

        $flywirePayment->reason = $data['failed_raw_reason']['description'] ?? null;
        $flywirePayment->reason_code = $data['failed_raw_reason']['code'] ?? null;
        $flywirePayment->cancellation_reason = $data['cancellation_reason'] ?? null;
        $flywirePayment->saveOrFail();
    }

    private function saveBankAccountPayment(string $paymentId, array $data, Money $money): ?Payment
    {
        $payment = new Payment();
        $payment->amount = $money->toDecimal();
        $payment->currency = $money->currency;
        $payment->date = (new CarbonImmutable($data['created_at']))->getTimestamp();
        $payment->method = PaymentMethodType::BankTransfer->toString();
        $payment->reference = $paymentId;
        $payment->source = PaymentFlowSource::CustomerPortal->toString();

        return $payment->save() ? $payment : null;
    }

    private function updateChargeStatus(Charge $charge, array $data): void
    {
        $status = Charge::PENDING;
        $message = null;

        if ('delivered' === $data['status'] || 'guaranteed' === $data['status']) {
            $status = Charge::SUCCEEDED;
        } elseif ('cancelled' === $data['status']) {
            $status = Charge::FAILED;
            $message = $data['cancellation_reason'];
        } elseif ('failed' === $data['status'] || 'reversed' === $data['status']) {
            $status = Charge::FAILED;
            if (isset($data['failed_raw_reason'])) {
                $message = $data['failed_raw_reason']['description'];
            }
        }

        if ($charge->status == $status) {
            return;
        }

        $this->updateChargeStatus->saveStatus($charge, $status, $message);
    }
}
