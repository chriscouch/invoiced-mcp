<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemittanceAdviceLine extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('RemittanceAdviceLines');
        $this->addTenant($table);
        $table->addColumn('remittance_advice_id', 'integer')
            ->addColumn('document_number', 'string')
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('gross_amount_paid', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('discount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('net_amount_paid', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('description', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('remittance_advice_id', 'RemittanceAdvice', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->create();
    }
}
