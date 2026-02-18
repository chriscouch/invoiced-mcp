<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;

abstract class AbstractCreditNoteTransformer extends AbstractTransformer
{
    public function getMappingObjectType(): string
    {
        return 'credit_note';
    }

    protected function makeRecord(AccountingRecordInterface $input, array $record): AccountingCreditNote
    {
        return TransformerHelper::makeCreditNote($this->syncProfile->getIntegrationType(), $record, $this->syncProfile->tenant());
    }
}
