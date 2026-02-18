<?php

use Phinx\Migration\AbstractMigration;

final class InvoiceTag extends AbstractMigration
{
    public function change()
    {
        $this->table('InvoiceTags', ['id' => false, 'primary_key' => ['invoice_id', 'tag']])
            ->addColumn('invoice_id', 'integer')
            ->addColumn('tag', 'string')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
