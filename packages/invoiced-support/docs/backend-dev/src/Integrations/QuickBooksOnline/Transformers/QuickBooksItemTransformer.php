<?php

namespace App\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingItem;
use App\Integrations\Enums\IntegrationType;

class QuickBooksItemTransformer extends AbstractTransformer
{
    public function getMappingObjectType(): string
    {
        return 'item';
    }

    protected function makeRecord(AccountingRecordInterface $input, array $record): AccountingItem
    {
        $accountingId = $record['accounting_id'];
        unset($record['accounting_id']);

        return new AccountingItem(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: $accountingId,
            values: $record,
        );
    }
}
