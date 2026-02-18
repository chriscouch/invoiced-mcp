<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Operations\CreateBill;
use App\AccountsPayable\Operations\EditBill;
use App\AccountsPayable\Operations\VoidBill;
use App\Core\Database\TransactionManager;

class BillImporter extends PayableDocumentImporter
{
    public function __construct(CreateBill $create, EditBill $edit, VoidBill $void, TransactionManager $transactionManager)
    {
        parent::__construct($create, $edit, $void, $transactionManager);
    }

    protected function getDocumentClass(): string
    {
        return Bill::class;
    }
}
