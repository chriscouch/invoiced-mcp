<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentFlowRelationFk extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableForeignKeyChecks();
        $this->table('PaymentFlowApplications')
            ->dropForeignKey('invoice_id')
            ->dropForeignKey('credit_note_id')
            ->dropForeignKey('estimate_id')
            ->update();

        $this->table('PaymentFlowApplications')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->update();
        $this->enableForeignKeyChecks();
    }
}
