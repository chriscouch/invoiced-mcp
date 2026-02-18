<?php

namespace App\PaymentProcessing\Operations;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Integrations\Adyen\AdyenConfiguration;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Exceptions\DisputeException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Reconciliation\DisputeReconciler;
use App\PaymentProcessing\ValueObjects\DisputeDocument;
use Symfony\Component\HttpFoundation\FileBag;

/**
 * Simple interface for processing refunds that handles
 * routing to the appropriate gateway and reconciliation.
 */
class DisputeOperations implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private readonly GatewayLogger $gatewayLogger,
        private readonly DisputeReconciler $disputeReconciler,
        private AdyenGateway $adyenGateway,
        private bool $adyenLiveMode,
    ) {
    }

    /**
     * Issues a refund for this charge.
     *
     * @throws DisputeException
     */
    public function defend(Dispute $dispute, string $reasonCode, FileBag $files, array $codes): void
    {
        // Request a refund through the payment gateway.
        $start = microtime(true);
        try {
            $defenceDocuments = $this->buildDocuments($files, $codes);
            $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $dispute->tenant()->country);

            $this->adyenGateway->supplyDefenseDocuments($adyenMerchantAccount, $dispute->gateway_id, $defenceDocuments);

            $this->adyenGateway->defendDispute($adyenMerchantAccount, $dispute->gateway_id, $reasonCode);

            $dispute->defense_reason = $reasonCode;
            $this->disputeReconciler->updateStatus($dispute, DisputeStatus::Responded);
        } catch (DisputeException $e) {
            $this->statsd->increment('payments.failed_dispute', 1, ['gateway' => $dispute->gateway]);
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);

            throw new InvalidRequest($e->getMessage());
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
        $this->statsd->increment('payments.successful_dispute', 1, ['gateway' => $dispute->gateway]);
    }

    /**
     * Issues a refund for this charge.
     *
     * @throws DisputeException
     */
    public function accept(Dispute $dispute): bool
    {
        // Request a refund through the payment gateway.
        $start = microtime(true);
        try {
            $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $dispute->tenant()->country);
            $result = $this->adyenGateway->acceptDispute($adyenMerchantAccount, $dispute->gateway_id);
            $this->disputeReconciler->updateStatus($dispute, DisputeStatus::Accepted);
        } catch (DisputeException $e) {
            $this->statsd->increment('payments.failed_dispute', 1, ['gateway' => $dispute->gateway]);
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);

            throw $e;
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
        $this->statsd->increment('payments.successful_dispute', 1, ['gateway' => $dispute->gateway]);

        return $result;
    }

    /**
     * @throws DisputeException
     */
    public function supplyDocuments(Dispute $dispute, FileBag $files, array $codes): void
    {
        // Request a refund through the payment gateway.
        $start = microtime(true);
        try {
            $defenceDocuments = $this->buildDocuments($files, $codes);
            $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $dispute->tenant()->country);

            $this->adyenGateway->supplyDefenseDocuments($adyenMerchantAccount, $dispute->gateway_id, $defenceDocuments);
        } catch (DisputeException $e) {
            $this->statsd->increment('payments.failed_dispute_supply_document', 1, ['gateway' => $dispute->gateway]);
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);

            throw new InvalidRequest($e->getMessage());
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
        $this->statsd->increment('payments.successful_dispute_supply_document', 1, ['gateway' => $dispute->gateway]);
    }

    public function deleteDocuments(Dispute $dispute, string $defenseDocumentType): bool
    {
        // Request a refund through the payment gateway.
        $start = microtime(true);
        try {
            $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $dispute->tenant()->country);
            $result = $this->adyenGateway->deleteDefenceDocument($adyenMerchantAccount, $defenseDocumentType, $dispute->gateway_id);
        } catch (DisputeException $e) {
            $this->statsd->increment('payments.failed_dispute_supply_document', 1, ['gateway' => $dispute->gateway]);
            $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);

            throw $e;
        }

        $this->gatewayLogger->setLastResponseTiming(microtime(true) - $start);
        $this->statsd->increment('payments.successful_dispute_supply_document', 1, ['gateway' => $dispute->gateway]);

        return $result;
    }

    public function getCodes(Dispute $dispute): array
    {
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $dispute->tenant()->country);

        return $this->adyenGateway->getDefenceCodes($adyenMerchantAccount, $dispute->gateway_id);
    }

    /**
     * @param string[] $codes
     *
     * @return DisputeDocument[]
     */
    private function buildDocuments(FileBag $bag, array $codes): array
    {
        $documents = [];
        foreach ($bag->all('files') as $key => $file) {
            $documents[] = new DisputeDocument(
                content: base64_encode(file_get_contents($file->getPathname()) ?: ''),
                contentType: $file->getMimeType(),
                defenseDocumentTypeCode: $codes[$key],
            );
        }

        return $documents;
    }
}
