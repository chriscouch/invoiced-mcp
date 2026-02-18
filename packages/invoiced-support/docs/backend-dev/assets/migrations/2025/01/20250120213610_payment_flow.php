<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentFlow extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('PaymentFlows');
        $this->addTenant($table);
        $table->addColumn('identifier', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('method', 'string', ['length' => 32, 'null' => true, 'default' => null])
            ->addColumn('payment_source_type', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('payment_source_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('save_payment_source', 'boolean')
            ->addColumn('make_payment_source_default', 'boolean')
            ->addColumn('return_url', 'string', ['length' => 5000, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->addColumn('initiated_from', 'smallinteger')
            ->addColumn('payment_values', 'json')
            ->addColumn('payment_link_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('processing_started_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('canceled_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->addForeignKey('customer_id', 'Customers', 'id')
            ->addForeignKey('payment_link_id', 'PaymentLinks', 'id')
            ->create();

        $this->table('PaymentFlowApplications')
            ->addColumn('payment_flow_id', 'integer')
            ->addColumn('type', 'smallinteger')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('estimate_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('document_type', 'smallinteger', ['null' => true, 'default' => null])
            ->addForeignKey('payment_flow_id', 'PaymentFlows', 'id')
            ->addForeignKey('invoice_id', 'Invoices', 'id')
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id')
            ->addForeignKey('estimate_id', 'Estimates', 'id')
            ->create();
    }
}
