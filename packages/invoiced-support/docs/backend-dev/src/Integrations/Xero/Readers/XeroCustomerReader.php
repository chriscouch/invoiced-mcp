<?php

namespace App\Integrations\Xero\Readers;

use App\Integrations\AccountingSync\Traits\CustomerReaderTrait;

class XeroCustomerReader extends AbstractXeroReader
{
    use CustomerReaderTrait;

    public function getId(): string
    {
        return 'xero_contact';
    }
}
