<?php

namespace App\Integrations\BusinessCentral\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractCreditNoteTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;

class BusinessCentralCreditMemoTransformer extends AbstractCreditNoteTransformer
{
    public function getMappingObjectType(): string
    {
        return 'salesCreditMemo';
    }

    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // TODO: validate that calculated line item amounts match netAmount

        // Business Central does not make customer payments and how they are applied
        // available in a clean way through the API. There is no balance field on
        // a credit memo so we can set the balance to 0 only if we know it is paid.
        if ('Paid' == $input->document->status) {
            $record['balance'] = 0;
        }

        return parent::transformRecordCustom($input, $record);
    }
}
