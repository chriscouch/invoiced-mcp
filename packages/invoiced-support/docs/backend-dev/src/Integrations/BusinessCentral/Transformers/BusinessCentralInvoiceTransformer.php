<?php

namespace App\Integrations\BusinessCentral\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractInvoiceTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;

class BusinessCentralInvoiceTransformer extends AbstractInvoiceTransformer
{
    public function getMappingObjectType(): string
    {
        return 'salesInvoice';
    }

    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // TODO: validate that calculated line item amounts match netAmount

        return parent::transformRecordCustom($input, $record);
    }
}
