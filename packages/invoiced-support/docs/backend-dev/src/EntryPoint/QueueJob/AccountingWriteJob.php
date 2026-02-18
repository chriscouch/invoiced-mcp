<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Writers\AccountingWriterFactory;
use App\Integrations\Enums\IntegrationType;
use Psr\Log\LoggerAwareTrait;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelDeleted;
use App\Core\Orm\Event\ModelUpdated;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;

class AccountingWriteJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    use LoggerAwareTrait;

    const WRITE_TTL = 60;

    public function __construct(
        private AccountingWriterFactory $factory,
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * Launch the job.
     */
    public function perform(): void
    {
        $id = $this->args['id'];
        $class = $this->args['class'];
        $eventName = $this->args['eventName'];
        $integrationId = $this->args['accounting_system'];

        $lock = $this->lockFactory->createLock("$class:$id", self::WRITE_TTL, true);
        try {
            if (!$lock->acquire()) {
                return;
            }
        } catch (LockAcquiringException $e) {
            $this->logger->error('Failed to acquire accounting write job lock', ['exception' => $e]);
        }

        $obj = $class::queryWithoutMultitenancyUnsafe()
            ->where('id', $id)
            ->oneOrNull();

        if (!$obj) {
            return;
        }

        $obj->tenant()->useTimezone();

        $integrationType = IntegrationType::from($integrationId);
        $this->performWrite($obj, $eventName, $integrationType);

        $lock->release();
    }

    private function performWrite(AccountingWritableModelInterface $obj, string $eventName, IntegrationType $integrationType): void
    {
        $account = AccountingSyncModelFactory::getAccount($integrationType, $obj->tenant());
        if (!$account) {
            return;
        }

        $syncProfile = AccountingSyncModelFactory::getSyncProfile($integrationType, $obj->tenant());
        if (!$syncProfile) {
            return;
        }

        $writer = $this->factory->build($obj, $integrationType);
        if (!$writer->isEnabled($syncProfile)) {
            return;
        }

        switch ($eventName) {
            case ModelCreated::getName():
                $writer->create($obj, $account, $syncProfile);

                break;
            case ModelUpdated::getName():
                $writer->update($obj, $account, $syncProfile);

                break;
            case ModelDeleted::getName():
                $writer->delete($obj, $account, $syncProfile);

                break;
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 1 write per accounting system connection can be processed at a time.
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        $accountingSystem = $args['accounting_system'] ?? '_';

        return 'accounting_write:'.$accountingSystem.':'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
