<?php

namespace App\Integrations\NetSuite\Writers;

use App\CashApplication\Models\Transaction;
use App\Integrations\AccountingSync\AccountingMappingFactory;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Exception\ModelException;

/**
 * Class NetSuiteNoteAdapter.
 *
 * @template-extends    AbstractNetSuiteObjectWriter<Transaction>
 *
 * @property Transaction $model
 */
abstract class AbstractNetSuiteTransactionWriter extends AbstractNetSuiteObjectWriter
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    /**
     * @throws ModelException
     */
    public function reverseMap(object $response): void
    {
        if (!property_exists($response, 'id') || !$response->id) {
            return;
        }

        $model = $this->model;
        $metadata = $model->metadata;
        $metadata->{NetSuiteWriter::REVERSE_MAPPING} = $response->id;
        $model->metadata = $metadata;
        $model->saveOrFail();

        if ($mapping = AccountingMappingFactory::getInstance($model)) {
            $mapping->setIntegration(IntegrationType::NetSuite);
            $mapping->accounting_id = $response->id;
            $mapping->source = AbstractMapping::SOURCE_INVOICED;
            $mapping->save();
        }
    }

    /**
     * Gets reverse mapping metadata.
     */
    public function getReverseMapping(): ?string
    {
        $metadata = $this->model->metadata;

        return $metadata->{NetSuiteWriter::REVERSE_MAPPING} ?? null;
    }

    public function shouldCreate(): bool
    {
        return parent::shouldCreate() && null === $this->model->parent_transaction;
    }

    public function shouldUpdate(): bool
    {
        return false;
    }
}
