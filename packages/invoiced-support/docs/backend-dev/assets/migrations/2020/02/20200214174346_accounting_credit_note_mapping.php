<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingCreditNoteMapping extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountingCreditNoteMappings', ['id' => false, 'primary_key' => ['credit_note_id']]);
        $this->addTenant($table);
        $table->addColumn('credit_note_id', 'integer')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('accounting_id', 'string')
            ->addColumn('source', 'enum', ['values' => ['accounting_system', 'invoiced']])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
