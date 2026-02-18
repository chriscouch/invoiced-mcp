<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\AccountingMappingFactory;
use App\Integrations\AccountingSync\Enums\SyncDirection;
use App\Integrations\AccountingSync\IntegrationConfiguration;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncFieldMapping;
use App\Integrations\AccountingSync\ReadSync\TransformerHelper;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Integrations\Enums\IntegrationType;

/**
 * @template-extends   AbstractNetSuiteObjectWriter<ReceivableDocument|Customer|Payment>
 *
 * @phpstan-template T of AccountingWritableModelInterface
 */
abstract class AbstractNetSuiteCustomerObjectWriter extends AbstractNetSuiteObjectWriter
{
    abstract protected function getParentCustomer(): ?Customer;

    public function toArray(): array
    {
        $objectName = $this->model->getObjectName();
        $dataFlow = $objectName.'_write';
        $fields = IntegrationConfiguration::get()->getMapping(IntegrationType::NetSuite, $dataFlow);
        $customFields = AccountingSyncFieldMapping::getForDataFlow(IntegrationType::NetSuite, SyncDirection::Write, $objectName);
        foreach ($customFields as $customMapping) {
            $fields[] = new TransformField($customMapping->source_field, $customMapping->destination_field, $customMapping->data_type, value: $customMapping->value);
        }

        $data = TransformerHelper::transformModel($this->model, $fields);
        $data['netsuite_id'] = $this->getReverseMapping();
        if (isset($data['parent_customer']) && $parentCustomer = $this->getParentCustomer()) {
            $data['parent_customer']['netsuite_id'] = $this->getCustomerMapping($parentCustomer);
        }

        return $data;
    }

    public function reverseMap(object $response): void
    {
        if (!property_exists($response, 'id') || !$response->id) {
            return;
        }
        if ($mapping = AccountingMappingFactory::getInstance($this->model)) {
            $mapping->setIntegration(IntegrationType::NetSuite);
            $mapping->accounting_id = $response->id;
            $mapping->source = (property_exists($response, 'existing') && $response->existing) ? AbstractMapping::SOURCE_ACCOUNTING_SYSTEM : AbstractMapping::SOURCE_INVOICED;
            $mapping->save();
        }
    }
}
