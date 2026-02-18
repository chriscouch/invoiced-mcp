<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InitialImportFeatureFlag extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute("INSERT INTO Features SELECT NULL, tenant_id, 'intacct_initial_import', 1 FROM Imports WHERE type IN ('intacct_credit_note', 'intacct_customer', 'intacct_invoice', 'intacct_payment', 'intacct_sales_invoice') GROUP BY tenant_id ");
        $this->execute("INSERT INTO Features SELECT NULL, tenant_id, 'quickbooks_initial_import', 1 FROM Imports WHERE type IN ('quickbooks_customer', 'quickbooks_invoice', 'quickbooks_record') GROUP BY tenant_id ");
        $this->execute("INSERT INTO Features SELECT NULL, tenant_id, 'xero_initial_import', 1 FROM Imports WHERE type IN ('xero_customer', 'xero_invoice') GROUP BY tenant_id ");
    }
}
