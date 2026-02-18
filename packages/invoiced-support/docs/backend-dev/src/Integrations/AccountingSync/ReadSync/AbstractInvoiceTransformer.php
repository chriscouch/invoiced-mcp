<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;

abstract class AbstractInvoiceTransformer extends AbstractTransformer
{
    public function getMappingObjectType(): string
    {
        return 'invoice';
    }

    protected function makeRecord(AccountingRecordInterface $input, array $record): AccountingInvoice
    {
        return TransformerHelper::makeInvoice($this->syncProfile->getIntegrationType(), $record, $this->syncProfile->tenant());
    }
}
