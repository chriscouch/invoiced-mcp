<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Interfaces\AccountingReaderInterface;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Enums\IntegrationType;

abstract class AbstractReadSyncQueueJob extends AbstractResqueJob implements MaxConcurrencyInterface, TenantAwareQueueJobInterface
{
    /**
     * @param AccountingReaderInterface[] $readers
     */
    public function __construct(
        private iterable $readers,
        private ReadSync $readSync,
        private TenantContext $tenant,
    ) {
    }

    abstract protected function getIntegrationType(): IntegrationType;

    public function perform(): void
    {
        $integrationType = $this->getIntegrationType();
        $company = $this->tenant->get();
        $account = AccountingSyncModelFactory::getAccount($integrationType, $company);
        if (!$account) {
            return;
        }

        $syncProfile = AccountingSyncModelFactory::getSyncProfile($integrationType, $company);
        if (!$syncProfile) {
            return;
        }

        // Perform a Historical Sync, Ongoing Sync, or Single Object Sync
        if (isset($this->args['historical_sync'])) {
            // Determine the readers to use
            $enabledReaders = [];
            foreach ($this->readers as $reader) {
                if (in_array($reader->getId(), $this->args['readers'])) {
                    $enabledReaders[] = $reader;
                }
            }

            $query = ReadQuery::fromArray($this->args);

            $this->readSync->syncHistorical($account, $syncProfile, $enabledReaders, $query);
        } elseif (isset($this->args['single_sync'])) {
            // Find a matching reader based on the object type
            $reader = $this->getReader($this->args['reader'] ?? null, $this->args['object']);
            if ($reader) {
                // use the company's time zone for date stuff
                $syncProfile->tenant()->useTimezone();
                $reader->syncOne($account, $syncProfile, $this->args['accountingId']);
            }
        } else {
            $this->readSync->syncOngoing($account, $syncProfile, $this->readers);
        }
    }

    protected function getReader(?string $id, string $type): ?AccountingReaderInterface
    {
        foreach ($this->readers as $reader) {
            // Using the reader ID is preferred to matching on object type.
            if ($id) {
                if ($reader->getId() == $id) {
                    return $reader;
                }
            } else {
                if ($reader instanceof AbstractReader && $reader->invoicedObjectType()->typeName() == $type) {
                    return $reader;
                }
            }
        }

        return null;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'accounting_read_sync:'.$args['tenant_id'];
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyTtl(array $args): int
    {
        // Maximum is 24 hours. If a sync ever exceeds 24 hours
        // then it would result in concurrent syncs operating.
        return 86400;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
