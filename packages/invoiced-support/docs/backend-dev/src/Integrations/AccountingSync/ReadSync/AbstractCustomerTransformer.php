<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;

abstract class AbstractCustomerTransformer extends AbstractTransformer
{
    public function getMappingObjectType(): string
    {
        return 'customer';
    }

    protected function makeRecord(AccountingRecordInterface $input, array $record): AccountingCustomer
    {
        return TransformerHelper::makeCustomer($this->syncProfile->getIntegrationType(), $record);
    }
}
