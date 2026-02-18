<?php

namespace App\Integrations\ChartMogul;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use App\Integrations\ChartMogul\Syncs\AbstractSync;
use ChartMogul\Configuration;
use ChartMogul\DataSource;
use ChartMogul\Exceptions\ChartMogulException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class ChartMogulSync implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param AbstractSync[] $syncs
     */
    public function __construct(private iterable $syncs)
    {
    }

    public function run(ChartMogulAccount $account): void
    {
        $startTime = time();
        $account->tenant()->useTimezone();

        try {
            $this->setChartMogulAccount($account);
        } catch (ChartMogulException $e) {
            $account->last_sync_attempt = $startTime;
            $account->last_sync_error = $e->getMessage();
            $account->saveOrFail();

            return;
        }

        // run each individual sync and record any errors
        $hadFailure = false;
        foreach ($this->syncs as $sync) {
            try {
                $sync->sync($account);
            } catch (SyncException $e) {
                $account->last_sync_attempt = $startTime;
                $account->last_sync_error = $e->getMessage();
                $account->saveOrFail();
                $hadFailure = true;
            } catch (Throwable $e) {
                $this->logger->error('Uncaught exception in ChartMogul sync', ['exception' => $e]);
                $account->last_sync_attempt = $startTime;
                $account->last_sync_error = 'Internal Server Error';
                $account->saveOrFail();
                $hadFailure = true;
            }
        }

        if (!$hadFailure) {
            // only advance the cursor when all sync steps were successful
            $account->sync_cursor = $startTime;
            $account->last_sync_attempt = $startTime;
            $account->last_sync_error = null;
            $account->saveOrFail();
        }
    }

    /**
     * This should be called at the beginning of each sync to configure
     * the ChartMogul API client.
     *
     * @throws ChartMogulException
     */
    private function setChartMogulAccount(ChartMogulAccount $account): void
    {
        Configuration::getDefaultConfiguration()
            ->setApiKey($account->token);

        // Verify that the selected data source exists
        DataSource::retrieve($account->data_source);
    }
}
