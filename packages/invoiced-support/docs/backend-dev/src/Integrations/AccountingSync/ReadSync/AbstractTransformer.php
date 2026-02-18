<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Integrations\AccountingSync\Enums\SyncDirection;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\IntegrationConfiguration;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncReadFilter;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Core\Orm\Model;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

abstract class AbstractTransformer implements TransformerInterface
{
    protected AccountingSyncProfile $syncProfile;
    /** @var TransformField[] */
    protected array $mapping;
    /** @var AccountingSyncReadFilter[] */
    protected array $filters;
    private ExpressionLanguage $expressionLanguage;

    abstract public function getMappingObjectType(): string;

    /**
     * @return TransformField[]
     */
    public function getMapping(): array
    {
        $integration = $this->syncProfile->getIntegrationType();
        $dataFlow = $this->getMappingObjectType().'_read';

        return IntegrationConfiguration::get()->getMapping($integration, $dataFlow);
    }

    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->syncProfile = $syncProfile;

        $customMappings = AccountingSyncFieldMapping::getForDataFlow($syncProfile->getIntegrationType(), SyncDirection::Read, $this->getMappingObjectType());
        $this->mapping = $this->getMapping();
        foreach ($customMappings as $customMapping) {
            $this->mapping[] = new TransformField($customMapping->source_field, $customMapping->destination_field, $customMapping->data_type, value: $customMapping->value);
        }

        $this->filters = AccountingSyncReadFilter::where('integration', $syncProfile->getIntegrationType()->value)
            ->where('object_type', $this->getMappingObjectType())
            ->where('enabled', true)
            ->first(100);
    }

    /**
     * Converts an array record to an accounting record.
     */
    abstract protected function makeRecord(AccountingRecordInterface $input, array $record): AbstractAccountingRecord;

    public function transform(AccountingRecordInterface $input): ?AbstractAccountingRecord
    {
        if ($input instanceof AccountingJsonRecord) {
            $record = TransformerHelper::transformJson($input, $this->mapping);
        } elseif ($input instanceof AccountingXmlRecord) {
            $record = TransformerHelper::transformXml($input, $this->mapping);
        } else {
            throw new TransformException('Not supported record type');
        }

        // Allow the transformer to modify the record after the mapping
        // is applied.
        $record = $this->transformRecordCustom($input, $record);
        if (!$record) {
            return null;
        }

        // Apply the filter to the record
        foreach ($this->filters as $filter) {
            if (!$this->filterMatches($filter, $record)) {
                return null;
            }
        }

        return $this->makeRecord($input, $record);
    }

    /**
     * This gives transformers the opportunity to do a final manipulation or rejection
     * of a record.
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        return $record;
    }

    /**
     * Checks if a filter matches a record based on its formula.
     */
    protected function filterMatches(AccountingSyncReadFilter $filter, array $record): bool
    {
        try {
            if (!isset($this->expressionLanguage)) {
                $this->expressionLanguage = new ExpressionLanguage();
            }

            return (bool) @$this->expressionLanguage->evaluate($filter->formula, [
                'record' => (object) $record,
            ]);
        } catch (Throwable) {
            return false;
        }
    }
}
