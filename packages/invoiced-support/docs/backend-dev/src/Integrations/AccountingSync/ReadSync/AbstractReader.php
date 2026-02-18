<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Core\Database\TransactionManager;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\AccountingReaderInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\ValueObjects\AccountingObjectReference;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Core\Orm\Model;

abstract class AbstractReader implements AccountingReaderInterface
{
    abstract public static function getDefaultPriority(): int;

    public function __construct(
        protected TransactionManager $transactionManager,
        protected ExtractorInterface $extractor,
        protected TransformerInterface $transformer,
        protected AccountingLoaderFactory $loaderFactory,
    ) {
    }

    /**
     * Returns the object type as referred to by Invoiced.
     */
    abstract public function invoicedObjectType(): ObjectType;

    /**
     * Gets the name of this sync type for presentation to the user.
     */
    abstract protected function getDisplayName(AccountingSyncProfile $syncProfile): string;

    /**
     * Gets the accounting mapping class for this reader.
     *
     * @throws SyncException
     */
    protected function mappingClass(): string
    {
        return self::objectNameToMappingClass($this->invoicedObjectType());
    }

    /**
     * @throws SyncException
     */
    public static function objectNameToMappingClass(ObjectType $objectType): string
    {
        return match ($objectType) {
            ObjectType::CreditNote => AccountingCreditNoteMapping::class,
            ObjectType::Customer => AccountingCustomerMapping::class,
            ObjectType::Invoice => AccountingInvoiceMapping::class,
            ObjectType::Payment => AccountingPaymentMapping::class,
            default => throw new SyncException('Object type mapping not supported: '.$objectType->typeName()),
        };
    }

    /**
     * Queries database for an accounting mapping based on object type
     * accounting id.
     */
    protected function getMapping(int $integrationId, string $accountingId): ?AbstractMapping
    {
        /** @var AbstractMapping $class */
        $class = $this->mappingClass();

        return $class::where('accounting_id', $accountingId)
            ->where('integration_id', $integrationId)
            ->oneOrNull();
    }

    /**
     * Clear any previous system-level sync errors.
     */
    protected function clearReconciliationErrors(AccountingSyncProfile $syncProfile): void
    {
        ReconciliationError::where('object', $this->invoicedObjectType()->typeName())
            ->where('integration_id', $syncProfile->getIntegrationType()->value)
            ->where('object_id IS NULL')
            ->where('accounting_id IS NULL')
            ->delete();
    }

    /**
     * Create a system-level sync error when retrieving data fails.
     */
    protected function recordReconciliationErrors(AccountingSyncProfile $syncProfile, SyncException $e): void
    {
        ReconciliationError::makeHighLevelError(
            $syncProfile->getIntegrationType()->value,
            $this->invoicedObjectType(),
            $e->getMessage()
        );
    }

    /**
     * Handles successful sync of individual records.
     */
    protected function handleSyncSuccess(AccountingSyncProfile $syncProfile, string $accountingId, ObjectType $invoicedType): void
    {
        // Remove reconciliation error
        ReconciliationError::where('object', $invoicedType->typeName())
            ->where('accounting_id', $accountingId)
            ->where('integration_id', $syncProfile->getIntegrationType()->value)
            ->delete();
    }

    /**
     * Handler errors during syncing.
     * Creates retryable reconciliation error.
     *
     * @param string      $message    - error message
     * @param string|null $invoicedId - invoiced id of object
     */
    protected function handleSyncFailure(AccountingSyncProfile $syncProfile, string $accountingId, ObjectType $invoicedType, string $message, ?string $invoicedId = null): void
    {
        ReconciliationError::makeReadError(
            $this->getId(),
            new AccountingObjectReference($syncProfile->getIntegrationType(), $invoicedType->typeName(), $accountingId, $invoicedId),
            $message
        );
    }

    /**
     * @throws SyncException
     */
    protected function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing '.$this->getDisplayName($syncProfile));

        // Initialize the extractor and transformer
        $this->extractor->initialize($account, $syncProfile);
        $this->transformer->initialize($account, $syncProfile);
    }

    public function syncOne(Model $account, AccountingSyncProfile $syncProfile, string $objectId): void
    {
        $this->initialize($account, $syncProfile);
        try {
            $object = $this->extractor->getObject($objectId);
            $this->syncObject($syncProfile, $object, $objectId);
        } catch (ExtractException $e) {
            $invoicedId = null;
            if ($mapping = $this->getMapping($syncProfile->getIntegrationType()->value, $objectId)) {
                $invoicedId = (string) $mapping->id();
            }

            $this->handleSyncFailure($syncProfile, $objectId, $this->invoicedObjectType(), $e->getMessage(), $invoicedId);
        }
    }

    public function syncAll(Model $account, AccountingSyncProfile $syncProfile, ReadQuery $query): void
    {
        $this->initialize($account, $syncProfile);

        try {
            $this->clearReconciliationErrors($syncProfile);

            $objects = $this->extractor->getObjects($syncProfile, $query);
            foreach ($objects as $object) {
                $accountingId = $this->extractor->getObjectId($object);
                $this->syncObject($syncProfile, $object, $accountingId);
            }
        } catch (SyncException $e) {
            $this->recordReconciliationErrors($syncProfile, $e);

            // Rethrow to signal that the sync has failed in a way
            // that should not proceed with additional syncs.
            throw $e;
        }
    }

    /**
     * Reads and imports a single accounting system object.
     */
    protected function syncObject(AccountingSyncProfile $syncProfile, AccountingRecordInterface $object, string $accountingId): void
    {
        $type = $this->invoicedObjectType();

        try {
            if ($record = $this->transformer->transform($object)) {
                $this->transactionManager->perform(function () use ($record, $syncProfile, $accountingId, $type) {
                    $this->loaderFactory->get($record)->load($record);
                    $this->handleSyncSuccess($syncProfile, $accountingId, $type);
                });
            }
        } catch (TransformException|LoadException $e) {
            $invoicedId = null;
            if ($mapping = $this->getMapping($syncProfile->getIntegrationType()->value, $accountingId)) {
                $invoicedId = (string) $mapping->id();
            }

            $this->handleSyncFailure($syncProfile, $accountingId, $type, $e->getMessage(), $invoicedId);
        }
    }
}
