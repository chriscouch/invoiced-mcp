<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ScheduledSends extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ScheduledSends');
        $this->addTenant($table);

        $table->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('estimate_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('channel', 'smallinteger')
            ->addColumn('parameters', 'text', ['null' => true, 'default' => null])
            ->addColumn('send_after', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('sent', 'boolean')
            ->addColumn('canceled', 'boolean')
            ->addColumn('failed', 'boolean')
            ->addColumn('failure_detail', 'text', ['null' => true, 'default' => null])
            ->addColumn('ignore_failure', 'boolean')
            ->addColumn('sent_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('reference', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
