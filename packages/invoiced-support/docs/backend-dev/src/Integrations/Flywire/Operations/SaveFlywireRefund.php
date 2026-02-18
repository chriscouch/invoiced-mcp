<?php

namespace App\Integrations\Flywire\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Enums\FlywireRefundStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Integrations\Flywire\Models\FlywireRefund;
use App\Integrations\Flywire\Models\FlywireRefundBundle;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\Refund;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SaveFlywireRefund implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private FlywirePrivateClient $client,
    ) {
    }

    /**
     * Syncs a single Refund from Flywire to our database.
     *
     * @throws IntegrationApiException
     */
    public function sync(string $refundId, string $recipientId, bool $forceUpdate = false): void
    {
        // Retrieve the latest version of the Refund from the Flywire API.
        // Even if we get the data from a list API call, the status can
        // be incorrect due to latency. It is best to always fetch the
        // refund details because this shows real-time information.
        $data = $this->client->getRefund($refundId, $recipientId);

        // Check for an existing refund model, and create one if needed
        $flywireRefund = FlywireRefund::where('refund_id', $refundId)
            ->oneOrNull();
        if (!$flywireRefund) {
            $flywireRefund = new FlywireRefund();
        }

        /** @var ?Refund $refund */
        $refund = Refund::where('gateway_id', $refundId)
            ->where('gateway', FlywireGateway::ID)
            ->oneOrNull();

        // Update the Flywire refund record
        $this->saveRefund($flywireRefund, $data, $refund, $forceUpdate);

        // Update the refund status
        if ($refund) {
            $this->updateStatus($refund, $flywireRefund->status);
        }
    }

    private function saveRefund(FlywireRefund $flywireRefund, array $data, ?Refund $refund, bool $forceUpdate): void
    {
        // Attempt to link the Flywire refund to an Invoiced refund
        $hasChange = $forceUpdate;
        if (!$flywireRefund->ar_refund && $refund) {
            $flywireRefund->ar_refund = $refund;
            $hasChange = true;
        }

        // Attempt to link the Flywire refund to a Flywire payment
        if (!$flywireRefund->payment && isset($data['payment']['id'])) {
            $flywireRefund->payment = FlywirePayment::where('payment_id', $data['payment']['id'])->oneOrNull();
            if ($flywireRefund->payment) {
                $hasChange = true;
            }
        }

        if (!$flywireRefund->bundle && isset($data['bundle']['id'])) {
            $flywireRefund->bundle = FlywireRefundBundle::where('bundle_id', $data['bundle']['id'])->oneOrNull();
            if ($flywireRefund->bundle) {
                $hasChange = true;
            }
        }

        // Do not sync refund if the status already matches our database
        $status = FlywireRefundStatus::fromString($data['status']);
        if ($flywireRefund->persisted() && $flywireRefund->status == $status && !$hasChange) {
            return;
        }

        $flywireRefund->refund_id = $data['id'];
        $flywireRefund->recipient_id = $data['sender']['id'] ?? '';
        $flywireRefund->initiated_at = new CarbonImmutable($data['created_at']);
        $flywireRefund->setAmount(new Money($data['amount']['currency']['code'], $data['amount']['value']));
        $flywireRefund->setAmountTo(new Money($data['amount_to']['currency']['code'] ?? $data['amount']['currency']['code'], $data['amount_to']['value'] ?? 0));
        $flywireRefund->status = $status;
        $flywireRefund->saveOrFail();
    }

    private function updateStatus(Refund $refund, FlywireRefundStatus $flywireStatus): void
    {
        $status = match ($flywireStatus) {
            FlywireRefundStatus::Canceled, FlywireRefundStatus::Returned => 'failed',
            FlywireRefundStatus::Received, FlywireRefundStatus::Finished => 'succeeded',
            FlywireRefundStatus::Initiated, FlywireRefundStatus::Pending => 'pending',
        };

        if ($status == $refund->status) {
            return;
        }

        $refund->status = $status;
        $refund->saveOrFail();
    }
}
