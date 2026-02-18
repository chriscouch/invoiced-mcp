<?php

namespace App\Imports\Traits;

use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Integrations\AccountingSync\AccountingMappingFactory;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;

trait ImportAccountingParametersTrait
{
    /**
     * @throws ValidationException
     */
    protected function buildRecordAccounting(array $record): array
    {
        // validate accounting system value
        if (isset($record['accounting_system'])) {
            try {
                $record['accounting_system'] = IntegrationType::fromString($record['accounting_system']);
            } catch (IntegrationException $e) {
                throw new ValidationException($e->getMessage());
            }
        }

        return $record;
    }

    /**
     * Creates or updates an accounting mapping model.
     *
     * @throws RecordException
     */
    protected function saveAccountingMapping(Model $model, IntegrationType $integration, string $accountingId): void
    {
        $newMapping = AccountingMappingFactory::getInstance($model);
        if (!$newMapping) {
            return;
        }

        // Check for existing mapping
        /** @var AbstractMapping|null $mapping */
        $mapping = $newMapping::find($model->id());

        // Create a new one if there is not one already. The
        // source of a new mapping should be accounting system.
        if (!$mapping) {
            $mapping = $newMapping;
            $mapping->source = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;
        } else {
            // Do not update if no change in value
            if ($mapping->getIntegrationType() == $integration && $mapping->accounting_id == $accountingId) {
                return;
            }
        }

        $mapping->setIntegration($integration);
        $mapping->accounting_id = $accountingId;

        if (!$mapping->save()) {
            throw new RecordException('Could not save accounting mapping: '.$mapping->getErrors());
        }
    }
}
