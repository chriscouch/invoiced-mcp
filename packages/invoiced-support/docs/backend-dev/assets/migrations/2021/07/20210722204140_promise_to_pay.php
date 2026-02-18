<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PromiseToPay extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ExpectedPaymentDates')
            ->addColumn('customer_id', 'integer')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->removeColumn('notes')
            ->addColumn('reference', 'string', ['null' => true, 'default' => null])
            ->addColumn('kept', 'boolean')
            ->addColumn('broken', 'boolean')
            ->addIndex(['tenant_id', 'broken'])
            ->dropForeignKey('invoice_id')
            ->update();

        // Drop primary key
        $this->execute('ALTER TABLE ExpectedPaymentDates DROP PRIMARY KEY');

        // Add auto-increment primary key
        $this->execute('ALTER TABLE ExpectedPaymentDates ADD id INT AUTO_INCREMENT PRIMARY KEY');

        // Populate new columns from invoice
        $this->execute('UPDATE ExpectedPaymentDates E JOIN Invoices I on E.invoice_id = I.id SET E.customer_id=I.customer, E.amount=I.total, E.currency=I.currency');

        // Add customer FK
        $this->table('ExpectedPaymentDates')
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->update();
    }
}
