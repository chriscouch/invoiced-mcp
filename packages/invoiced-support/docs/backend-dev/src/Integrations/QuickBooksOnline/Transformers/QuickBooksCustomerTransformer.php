<?php

namespace App\Integrations\QuickBooksOnline\Transformers;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ReadSync\AbstractCustomerTransformer;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;

class QuickBooksCustomerTransformer extends AbstractCustomerTransformer
{
    /**
     * @param AccountingJsonRecord $input
     */
    protected function transformRecordCustom(AccountingRecordInterface $input, array $record): ?array
    {
        // strip customer # from name, if present
        $name = (string) $record['name'];
        $i = strpos($name, 'CUST-');
        if (false !== $i) {
            $name = substr($name, 0, $i);
        }
        $record['name'] = trim($name);

        return $record;
    }
}
