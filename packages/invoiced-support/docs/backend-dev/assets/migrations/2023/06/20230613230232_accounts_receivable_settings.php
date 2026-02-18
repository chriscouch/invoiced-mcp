<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountsReceivableSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AccountsReceivableSettings', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('add_payment_plan_on_import', 'string', ['null' => true, 'default' => null])
            ->addColumn('aging_buckets', 'string', ['default' => '[0,7,14,30,60]'])
            ->addColumn('aging_date', 'enum', ['values' => ['date', 'due_date'], 'default' => 'date'])
            ->addColumn('allow_chasing', 'boolean')
            ->addColumn('auto_apply_credits', 'boolean')
            ->addColumn('autopay_delay_days', 'integer', ['default' => 0])
            ->addColumn('bcc', 'string')
            ->addColumn('chase_new_invoices', 'boolean')
            ->addColumn('chase_schedule', 'text')
            ->addColumn('debit_cards_only', 'boolean')
            ->addColumn('default_collection_mode', 'enum', ['values' => ['auto', 'manual'], 'default' => 'manual'])
            ->addColumn('default_consolidated_invoicing', 'boolean')
            ->addColumn('default_template_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('default_theme_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('email_provider', 'enum', ['values' => ['invoiced', 'smtp', 'null'], 'default' => 'invoiced'])
            ->addColumn('payment_retry_schedule', 'string', ['default' => '[3,5,7]'])
            ->addColumn('payment_terms', 'string', ['null' => true, 'default' => null])
            ->addColumn('reply_to_inbox_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('saved_cards_require_cvc', 'boolean')
            ->addColumn('tax_calculator', 'enum', ['default' => 'invoiced', 'values' => ['invoiced', 'avalara']])
            ->addColumn('transactions_inherit_invoice_metadata', 'boolean')
            ->addColumn('unit_cost_precision', 'integer', ['length' => 1, 'null' => true, 'default' => null])
            ->addForeignKey('reply_to_inbox_id', 'Inboxes', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
