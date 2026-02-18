<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceChasing extends MultitenantModelMigration
{
    public function change()
    {
        // contact model
        $this->table('Contacts')
            ->changeColumn('address1', 'text', ['null' => true, 'default' => null])
            ->addColumn('send_new_invoices', 'boolean', ['default' => false])
            ->update();

        // invoice chasing cadences
        $chasingCadences = $this->table('InvoiceChasingCadences');
        $this->addTenant($chasingCadences);
        $chasingCadences->addColumn('name', 'string')
            ->addColumn('chase_schedule', 'text')
            ->addColumn('default', 'boolean', ['default' => false])
            ->addTimestamps()
            ->create();

        // invoice deliveries
        $invoiceDeliveries = $this->table('InvoiceDeliveries');
        $this->addTenant($invoiceDeliveries);
        $invoiceDeliveries->addColumn('invoice_id', 'integer')
            ->addColumn('emails', 'text', ['null' => true, 'default' => null])
            ->addColumn('chase_schedule', 'text')
            ->addColumn('processed', 'boolean')
            ->addColumn('disabled', 'boolean')
            ->addColumn('last_sent_email', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_sent_sms', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_sent_letter', 'datetime', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'invoice_id'], ['unique' => true])
            ->create();

        // scheduled sends
        $this->table('ScheduledSends')
            ->addColumn('skipped', 'boolean')
            ->addColumn('replacement_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('replacement_id', 'ScheduledSends', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['tenant_id', 'replacement_id'])
            ->update();
    }
}
