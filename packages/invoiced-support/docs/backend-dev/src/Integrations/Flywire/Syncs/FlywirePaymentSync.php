<?php

namespace App\Integrations\Flywire\Syncs;

use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Interfaces\FlywireSyncInterface;
use App\Integrations\Flywire\Operations\SaveFlywirePayment;
use App\PaymentProcessing\Models\MerchantAccount;
use Carbon\CarbonImmutable;
use Generator;

class FlywirePaymentSync implements FlywireSyncInterface
{
    public static function getDefaultPriority(): int
    {
        return 20; // should go before refunds
    }

    public function __construct(
        private readonly FlywirePrivateClient $client,
        private readonly SaveFlywirePayment $savePayment,
    ) {
    }

    public function sync(MerchantAccount $merchantAccount, array $portalCodes, bool $fullSync): void
    {
        foreach ($this->getPayments($portalCodes, $fullSync) as $payment) {
            try {
                $this->savePayment->sync($payment['id'], $merchantAccount, $fullSync);
            } catch (IntegrationApiException) {
                // Continue to process next record. The exception is already logged.
            }
        }
    }

    /**
     * Gets all payments created within the last 30 days.
     */
    private function getPayments(array $portalCodes, bool $fullSync): Generator
    {
        $page = 1;
        $hasMore = true;

        $query = [
            'search' => [
                'recipient' => [
                    'id' => $portalCodes,
                ],
            ],
            'pagination' => [
                'per_page' => 100,
                'page' => 1,
            ],
        ];

        if (!$fullSync) {
            $query['search']['created_at'] = [
                '_from' => CarbonImmutable::now()->subDays(30)->toDateString(),
            ];
        }

        while ($hasMore) {
            try {
                $query['pagination']['page'] = $page;
                $result = $this->client->getPayments($query);
                $payments = $result['payments'];
                $hasMore = count($payments) >= 100;
                ++$page;

                yield from $payments;
            } catch (IntegrationApiException) {
                // Return so that other syncs may proceed. The exception is already logged.
                return;
            }
        }
    }
}
