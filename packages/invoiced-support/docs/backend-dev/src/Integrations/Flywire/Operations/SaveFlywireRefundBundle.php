<?php

namespace App\Integrations\Flywire\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Enums\FlywireRefundBundleStatus;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Models\FlywireRefundBundle;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SaveFlywireRefundBundle implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private FlywirePrivateClient $client,
    ) {
    }

    /**
     * Syncs a single Refund Bundle from Flywire to our database.
     *
     * @throws IntegrationApiException
     */
    public function sync(array $refundBundle, bool $forceUpdate = false): void
    {
        $refundBundleId = $refundBundle['id'];
        // see recipient_id: refund_bundle.dig('portals', 0),
        // in v1/shared/refund_bundles/refund_bundle_response.rb
        $recipient = $refundBundle['portals'][0];

        // Retrieve the latest version of the Refund Bundle from the Flywire API.
        // Even if we get the data from a list API call, the status can
        // be incorrect due to latency. It is best to always fetch the
        // refund details because this shows real-time information.
        $data = $this->client->getRefundBundle($refundBundleId, $recipient);

        // Check for an existing refund bundle model, and create one if needed
        $flywireRefundBundle = FlywireRefundBundle::where('bundle_id', $refundBundleId)
            ->oneOrNull();
        if (!$flywireRefundBundle) {
            $flywireRefundBundle = new FlywireRefundBundle();
        }

        // Update the Flywire refund bundle record
        $this->saveRefundBundle($flywireRefundBundle, $data, $recipient, $refundBundle['created_at'], $forceUpdate);
    }

    private function saveRefundBundle(FlywireRefundBundle $flywireRefundBundle, array $data, string $recipient, string $createdAt, bool $forceUpdate): void
    {
        // Do not sync refund bundle if the status already matches our database
        $status = FlywireRefundBundleStatus::fromString($data['status']);
        if (!$forceUpdate && $flywireRefundBundle->persisted() && $flywireRefundBundle->status == $status) {
            return;
        }

        $flywireRefundBundle->bundle_id = $data['id'];
        $flywireRefundBundle->recipient_id = $recipient;
        $flywireRefundBundle->initiated_at = new CarbonImmutable($createdAt);
        // this is not supported in v3
        // look for refund_cutoff_bundle = PaymentsApi::V1::Queries::RefundsCutOff::Fetch.do(params[:id])
        $flywireRefundBundle->marked_for_approval = $data['marked_for_approval'] ?? false;
        $flywireRefundBundle->setAmount(new Money($data['amount']['currency']['code'] ?? 'USD', $data['amount']['value'] ?? 0));
        $flywireRefundBundle->recipient_date = isset($data['reception']['date']) ? new CarbonImmutable($data['reception']['date']) : null;
        $flywireRefundBundle->recipient_bank_reference = $data['reception']['bank_reference'] ?? null;
        $flywireRefundBundle->recipient_account_number = $data['reception']['account_number'] ?? null;

        $recipientAmount = $data['reception']['amount']['value'] ?? null;
        $recipientCurrency = $data['reception']['currency']['currency']['code'] ?? null;
        if ($recipientCurrency && $recipientAmount) {
            $flywireRefundBundle->setRecipientAmount(new Money($recipientCurrency, $recipientAmount));
        }
        $flywireRefundBundle->status = $status;
        $flywireRefundBundle->saveOrFail();
    }
}
