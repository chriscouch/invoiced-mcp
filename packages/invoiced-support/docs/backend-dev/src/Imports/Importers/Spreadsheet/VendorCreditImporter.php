<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Operations\CreateVendorCredit;
use App\AccountsPayable\Operations\EditVendorCredit;
use App\AccountsPayable\Operations\VoidVendorCredit;
use App\Core\Database\TransactionManager;

class VendorCreditImporter extends PayableDocumentImporter
{
    public function __construct(CreateVendorCredit $create, EditVendorCredit $edit, VoidVendorCredit $void, TransactionManager $transactionManager)
    {
        parent::__construct($create, $edit, $void, $transactionManager);
    }

    protected function getDocumentClass(): string
    {
        return VendorCredit::class;
    }
}
