<?php

namespace App\Integrations\BusinessCentral\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractCustomerTransformer;

class BusinessCentralCustomerTransformer extends AbstractCustomerTransformer
{
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // Transform type
        if (isset($record['type']) && 'Company' == $record['type']) {
            $record['type'] = 'company';
        }
        if (isset($record['type']) && 'Person' == $record['type']) {
            $record['type'] = 'person';
        }

        return $record;
    }
}
