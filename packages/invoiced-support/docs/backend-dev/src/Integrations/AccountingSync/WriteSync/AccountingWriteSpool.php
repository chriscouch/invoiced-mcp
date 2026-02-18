<?php

namespace App\Integrations\AccountingSync\WriteSync;

use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\AccountingWriteJob;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;

class AccountingWriteSpool
{
    private const MAX_QUEUE_SIZE = 100;

    /**
     * @var IntegrationType[][]
     */
    private static array $accountingSystems = [];
    private static bool $enabled = true;

    private array $spool = [];

    /**
     * Enables write spool.
     */
    public static function enable(): void
    {
        self::$enabled = true;
    }

    /**
     * Enables write spool.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    public function __construct(private Queue $queue)
    {
    }

    public function __destruct()
    {
        $this->flush();
    }

    /**
     * Queues a model to be reconciled.
     */
    public function enqueue(AccountingWritableModelInterface $model, string $eventName, Company $company): void
    {
        // Check if writing is disabled globally.
        if (!self::$enabled) {
            return;
        }

        // If the user does not have the accounting_sync feature enabled
        // then don't bother queuing it.
        if (!$company->features->has('accounting_sync')) {
            return;
        }

        if (!isset(self::$accountingSystems[$company->id])) {
            self::$accountingSystems[$company->id] = [];
            $integrations = (new IntegrationFactory());
            foreach (IntegrationType::accountingIntegrations() as $integrationType) {
                $integration = $integrations->get($integrationType, $company);
                if ($integration->isConnected()) {
                    self::$accountingSystems[$company->id][] = $integrationType;
                }
            }
        }

        foreach (self::$accountingSystems[$company->id] as $integrationType) {
            // Only sync transactions with the NetSuite integration.
            // This is needed to support legacy versions.
            if ($model instanceof Transaction && IntegrationType::NetSuite != $integrationType) {
                continue;
            }

            $key = $model::class.'/'.$model->id().'/'.$integrationType->value; /* @phpstan-ignore-line */

            // A single model event will ensure the latest data
            // is reconciled. It's unnecessary to add a model
            // the queue more than once.
            if (isset($this->spool[$key])) {
                continue;
            }

            $this->spool[$key] = [$model, $eventName, $integrationType->value];
        }

        if (count($this->spool) >= self::MAX_QUEUE_SIZE) {
            $this->flush();
        }
    }

    /**
     * Gets the size of the spool.
     */
    public function size(): int
    {
        return count($this->spool);
    }

    /**
     * Clears the spool.
     */
    public function clear(): void
    {
        $this->spool = [];
    }

    /**
     * Returns the element at the beginning of the spool.
     */
    public function peek(): ?array
    {
        if (0 === $this->size()) {
            return null;
        }

        // Current should always be at the
        // first element of the spool.
        return current($this->spool);
    }

    /**
     * Sends models in the spool for reconciliation.
     */
    public function flush(): void
    {
        while (count($this->spool) > 0) {
            [$model, $eventName, $integrationId] = array_shift($this->spool);
            $this->queue->enqueue(AccountingWriteJob::class, [
                'id' => $model->id,
                'class' => $model::class,
                'eventName' => $eventName,
                'tenant_id' => $model->tenant_id,
                'accounting_system' => $integrationId,
            ]);
        }
    }
}
