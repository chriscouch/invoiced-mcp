<?php

namespace App\Integrations\Flywire\Syncs;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Interfaces\FlywireSyncInterface;
use App\Integrations\Flywire\Operations\SaveFlywireRefund;
use App\PaymentProcessing\Models\MerchantAccount;
use Carbon\CarbonImmutable;
use Generator;

class FlywireRefundSync implements FlywireSyncInterface
{
    public static function getDefaultPriority(): int
    {
        return 10;
    }

    public function __construct(
        private readonly FlywirePrivateClient $client,
        private readonly SaveFlywireRefund $saveRefund,
    ) {
    }

    public function sync(MerchantAccount $merchantAccount, array $portalCodes, bool $fullSync): void
    {
        foreach ($this->getRefunds($portalCodes, $fullSync) as $refund) {
            try {
                if (isset($refund['sender']['id'])) {
                    $this->saveRefund->sync($refund['id'], $refund['sender']['id'], $fullSync);
                }
            } catch (IntegrationApiException) {
                // Continue to process next record. The exception is already logged.
            }
        }
    }

    /**
     * Gets all refunds created within the last 30 days.
     */
    private function getRefunds(array $portalCodes, bool $fullSync): Generator
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
                '_to' => CarbonImmutable::now()->addDay()->toDateString(),
            ];
        }

        while ($hasMore) {
            try {
                $result = $this->client->getRefunds($query);
                $refunds = $result['refunds'];
                $hasMore = count($refunds) >= 100;
                ++$page;

                yield from $refunds;
            } catch (IntegrationApiException) {
                // Return so that other syncs may proceed. The exception is already logged.
                return;
            }
        }
    }
}
