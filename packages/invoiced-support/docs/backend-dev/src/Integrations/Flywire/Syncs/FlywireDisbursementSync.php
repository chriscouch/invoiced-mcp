<?php

namespace App\Integrations\Flywire\Syncs;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\FlywirePrivateClient;
use App\Integrations\Flywire\Interfaces\FlywireSyncInterface;
use App\Integrations\Flywire\Operations\SaveFlywireDisbursement;
use App\PaymentProcessing\Models\MerchantAccount;
use Carbon\CarbonImmutable;
use Generator;

class FlywireDisbursementSync implements FlywireSyncInterface
{
    public static function getDefaultPriority(): int
    {
        return 5;
    }

    public function __construct(
        private readonly FlywirePrivateClient $client,
        private readonly SaveFlywireDisbursement $saveFlywireDisbursement,
    ) {
    }

    public function sync(MerchantAccount $merchantAccount, array $portalCodes, bool $fullSync): void
    {
        foreach ($this->getDisbursements($portalCodes, $fullSync) as $response) {
            try {
                $this->saveFlywireDisbursement->sync($response, $fullSync);
            } catch (IntegrationApiException) {
                // Continue to process next record. The exception is already logged.
            }
        }
    }

    private function getDisbursements(array $portalCodes, bool $fullSync): Generator
    {
        $perPage = 100;
        $page = 1;
        $hasMore = true;
        $query = [
            'search' => [
                'recipients' => $portalCodes,
            ],
            'pagination' => [
                'per_page' => $perPage,
            ],
        ];

        if (!$fullSync) {
            $query['search']['initiated_start_date'] = CarbonImmutable::now()->subDays(30)->toDateString();
            $query['search']['initiated_end_date'] = CarbonImmutable::now()->addDay()->toDateString();
        }

        while ($hasMore) {
            try {
                $query['pagination']['page'] = $page;
                $result = $this->client->getDisbursements($query);
                $disbursements = $result['disbursements'];
                $hasMore = count($disbursements) >= $perPage;
                ++$page;

                yield from $disbursements;
            } catch (IntegrationApiException) {
                // Return so that other syncs may proceed. The exception is already logged.
                return;
            }
        }
    }
}
