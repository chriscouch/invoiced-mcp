<?php

namespace App\Integrations\AccountingSync\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;

abstract class AbstractAccountingSyncStatusRoute extends AbstractRetrieveModelApiRoute
{
    const MAPPING_CLASS = '';
    const MAPPING_ID = '';

    public function buildResponse(ApiCallContext $context): mixed
    {
        $model = parent::buildResponse($context);
        $mapping = $this->getMapping($model);

        // if no mapping was found check for a legacy mapping using metadata
        if (!$mapping) {
            $mapping = $this->getLegacyMappingFromMetadata($model);
        }

        if ($mapping) {
            return [
                'synced' => true,
                'error' => $this->getReconciliationError($model),
                'accounting_system' => $mapping->getIntegrationType()->toString(),
                'accounting_id' => $mapping->accounting_id,
                'source' => $mapping->source,
                'first_synced' => $mapping->created_at,
                'last_synced' => $mapping->updated_at,
            ];
        }

        return [
            'synced' => false,
            'error' => $this->getReconciliationError($model),
        ];
    }

    private function getMapping(AccountingWritableModelInterface $model): ?AbstractMapping
    {
        $class = static::MAPPING_CLASS;

        return $class::find($model->id()); /* @phpstan-ignore-line */
    }

    private function getLegacyMappingFromMetadata(AccountingWritableModelInterface $model): ?AbstractMapping
    {
        $accountingSystem = null;
        $metadata = $model->metadata; /* @phpstan-ignore-line */
        $id = null;
        $metadataKeys = $this->getLegacyMetadataKeys();
        foreach ($metadataKeys as $k => $integration) {
            if (property_exists($metadata, $k)) {
                $accountingSystem = $integration;
                $id = $metadata->$k;
            }
        }

        if (!$accountingSystem) {
            return null;
        }

        $class = static::MAPPING_CLASS;
        /** @var AbstractMapping $mapping */
        $mapping = new $class([
            static::MAPPING_ID => $model->id(), /* @phpstan-ignore-line */
            'integration_id' => $accountingSystem,
            'accounting_id' => $id,
            'source' => AbstractMapping::SOURCE_ACCOUNTING_SYSTEM,
            'created_at' => null,
            'updated_at' => null,
        ]);

        return $mapping;
    }

    protected function getLegacyMetadataKeys(): array
    {
        return [];
    }

    private function getReconciliationError(AccountingWritableModelInterface $model): ?array
    {
        /* @phpstan-ignore-next-line */
        $error = ReconciliationError::where('object', $model->object)
            ->where('object_id', $model)
            ->oneOrNull();

        return $error ? $error->toArray() : null;
    }
}
