<?php

namespace App\Integrations\FreshBooks\Transformers;

use App\Integrations\AccountingSync\ReadSync\AbstractCustomerTransformer;

class FreshBooksClientTransformer extends AbstractCustomerTransformer
{
    public function getMappingObjectType(): string
    {
        return 'client';
    }
}
