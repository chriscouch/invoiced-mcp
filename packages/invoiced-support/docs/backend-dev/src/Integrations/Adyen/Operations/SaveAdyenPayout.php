<?php

namespace App\Integrations\Adyen\Operations;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\Payout;
use App\PaymentProcessing\Reconciliation\PayoutReconciler;
use Carbon\CarbonImmutable;

class SaveAdyenPayout
{
    public function __construct(
        private AdyenClient $adyenClient,
        private PayoutReconciler $payoutReconciler,
    ) {
    }

    /**
     * Checks if a transfer is a payout based on category, type, and status.
     */
    public static function isPayout(array $transfer): bool
    {
        if ('bank' != $transfer['category']) {
            return false;
        }

        if (!in_array($transfer['type'], ['bankTransfer', 'bankDirectDebit', 'chargeback'])) {
            return false;
        }

        if (!in_array($transfer['status'], ['booked', 'rejected', 'returned', 'chargeback'])) {
            return false;
        }

        return true;
    }

    /**
     * @throws AdyenReconciliationException
     */
    public function save(string $transferId, MerchantAccount $merchantAccount, ?Money $pending = null): ?Payout
    {
        // Load the transfer via the Transfers API
        try {
            $transfer = $this->adyenClient->getTransfer($transferId);
        } catch (IntegrationApiException $e) {
            throw new AdyenReconciliationException($e->getMessage(), $transferId);
        }

        // Check if the transfer is a payout that can be created
        if (!self::isPayout($transfer)) {
            return null;
        }

        // TODO: need to handle type = bankDirectDebit
        if ('bankTransfer' != $transfer['type'] && 'chargeback' != $transfer['type']) {
            throw new AdyenReconciliationException('Creating payout for transfer '.$transferId.' with type not implemented: '.$transfer['type'], $transferId);
        }

        return $this->payoutReconciler->reconcile($merchantAccount, $this->payoutValues($transfer, $pending));
    }

    /**
     * @throws AdyenReconciliationException
     */
    private function payoutValues(array $transfer, ?Money $pending): array
    {
        $amount = new Money($transfer['amount']['currency'], $transfer['amount']['value']);
        $transferInstrumentId = $transfer['counterparty']['transferInstrumentId'] ?? '';

        $status = match ($transfer['status']) {
            'booked' => PayoutStatus::Completed,
            'rejected' => PayoutStatus::Canceled,
            'returned' => PayoutStatus::Failed,
            'chargeback' => PayoutStatus::Canceled,
            default => throw new AdyenReconciliationException('Creating payout for transfer '.$transfer['id'].' with status not implemented: '.$transfer['status'], $transfer['id']),
        };

        $values = [
            'reference' => $transfer['id'],
            'currency' => $amount->currency,
            'amount' => $amount->toDecimal(),
            'description' => 'Flywire Payout',
            'status' => $status,
            'initiated_at' => $this->getValueDate($transfer),
            'arrival_date' => isset($transfer['tracking']['estimatedArrivalTime']) ? new CarbonImmutable($transfer['tracking']['estimatedArrivalTime']) : null,
            'bank_account_name' => $this->adyenClient->getBankAccountName($transferInstrumentId),
            'modification_reference' => isset($transfer['categoryData']['modificationPspReference']) ? $transfer['categoryData']['modificationPspReference'] : null,
        ];

        // We only know pending amount from the Rolling Balance column in the payout report.
        // Unless it is explicitly provided then it is ignored.
        if ($pending) {
            $values['pending_amount'] = $pending->toDecimal();
        }

        return $values;
    }

    /**
     * @throws AdyenReconciliationException
     */
    private function getValueDate(array $transfer): CarbonImmutable
    {
        foreach ($transfer['events'] as $event) {
            if ('booked' == $event['status'] || 'chargeback' == $event['status']) {
                return new CarbonImmutable($event['valueDate']);
            }
        }

        throw new AdyenReconciliationException('Unable to get value date for transfer: '.$transfer['id'], $transfer['id']);
    }
}
