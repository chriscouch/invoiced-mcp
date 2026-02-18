<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;

abstract class AbstractPaymentTransformer extends AbstractTransformer
{
    public function getMappingObjectType(): string
    {
        return 'payment';
    }

    protected function makeRecord(AccountingRecordInterface $input, array $record): AccountingPayment
    {
        return TransformerHelper::makePayment($this->syncProfile->getIntegrationType(), $record, $this->syncProfile->tenant());
    }
}
