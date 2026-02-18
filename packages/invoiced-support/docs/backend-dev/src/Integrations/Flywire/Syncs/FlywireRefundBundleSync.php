<?php

namespace App\Integrations\Flywire\Syncs;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Interfaces\FlywireSyncInterface;
use App\Integrations\Flywire\Operations\SaveFlywireRefundBundle;
use App\PaymentProcessing\Models\MerchantAccount;
use Carbon\CarbonImmutable;
use Generator;

class FlywireRefundBundleSync implements FlywireSyncInterface
{
    public static function getDefaultPriority(): int
    {
        return 30; // should go before refunds
    }

    public function __construct(
        private readonly FlywirePrivateClient $client,
        private readonly SaveFlywireRefundBundle $saveRefundBundle,
    ) {
    }

    public function sync(MerchantAccount $merchantAccount, array $portalCodes, bool $fullSync): void
    {
        foreach ($this->getRefundBundles($portalCodes, $fullSync) as $refundBundle) {
            try {
                $this->saveRefundBundle->sync($refundBundle, $fullSync);
            } catch (IntegrationApiException) {
                // Continue to process next record. The exception is already logged.
            }
        }
    }

    /**
     * Gets all refund bundles created within the last 30 days.
     */
    private function getRefundBundles(array $portalCodes, bool $fullSync): Generator
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
                $query['pagination']['page'] = $page;
                $result = $this->client->getRefundBundles($query);
                $refundBundles = $result['bundles'];
                $hasMore = count($refundBundles) >= 100;
                ++$page;

                yield from $refundBundles;
            } catch (IntegrationApiException) {
                // Return so that other syncs may proceed. The exception is already logged.
                return;
            }
        }
    }
}
