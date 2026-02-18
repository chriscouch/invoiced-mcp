<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ImportedObjectTenancy extends MultitenantModelMigration
{
    public function change()
    {
        // Remove foreign key on import, add tenant_id column
        $this->table('ImportedObjects')
            ->dropForeignKey('import')
            ->addColumn('tenant_id', 'integer')
            ->addColumn('object_type', 'integer')
            ->update();

        // Drop primary key
        $this->execute('ALTER TABLE ImportedObjects DROP PRIMARY KEY');

        // Set tenant_id equal to that of the import's tenant_id.
        $this->execute('UPDATE ImportedObjects io INNER JOIN Imports i on io.import = i.id SET io.tenant_id = i.tenant_id');

        // Re-add import foreign key, add tenant_id foreign key
        $this->table('ImportedObjects')
            ->addForeignKey('import', 'Imports', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('tenant_id', 'Companies', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();

        // Add primary key on column named 'id'
        $this->execute('ALTER TABLE ImportedObjects ADD id INT(11) PRIMARY KEY AUTO_INCREMENT');

        // Set 'object_type' value to integer type of 'object' value
        $this->execute("
            UPDATE ImportedObjects SET object_type = 0 WHERE object IN ('netsuite', 'netsuite_record');
            UPDATE ImportedObjects SET object_type = 1 WHERE object IN ('customer', 'intacct_customer', 'quickbooks_customer', 'xero_customer', 'netsuite_customer', 'stripe_customer');
            UPDATE ImportedObjects SET object_type = 2 WHERE object IN ('invoice', 'intacct_invoice', 'intacct_sales_invoic', 'intacct_sales_invoice', 'quickbooks_invoice', 'netsuite_invoice', 'xero_invoice');
            UPDATE ImportedObjects SET object_type = 3 WHERE object IN ('credit_note', 'intacct_credit_note');
            UPDATE ImportedObjects SET object_type = 4 WHERE object = 'estimate';
            UPDATE ImportedObjects SET object_type = 5 WHERE object = 'subscription';
            UPDATE ImportedObjects SET object_type = 6 WHERE object IN ('transaction', 'intacct_payment');
            UPDATE ImportedObjects SET object_type = 7 WHERE object IN ('payment', 'bai_payment');
            UPDATE ImportedObjects SET object_type = 8 WHERE object IN ('catalog_item', 'stored_item');
            UPDATE ImportedObjects SET object_type = 9 WHERE object = 'plan';
            UPDATE ImportedObjects SET object_type = 10 WHERE object = 'tax_rate';
            UPDATE ImportedObjects SET object_type = 11 WHERE object = 'coupon';
            UPDATE ImportedObjects SET object_type = 16 WHERE object = 'payment_source';
            UPDATE ImportedObjects SET object_type = 17 WHERE object = 'pending_line_item';       
        ");

        // Drop object column, add index (tenant_id, import)
        $this->table('ImportedObjects')
            ->removeColumn('object')
            ->update();

        $this->table('ImportedObjects')
            ->renameColumn('object_type', 'object')
            ->addIndex(['tenant_id', 'import'])
            ->update();
    }
}
