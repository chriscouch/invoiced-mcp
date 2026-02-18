<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Role extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Roles', ['id' => false, 'primary_key' => ['tenant_id', 'id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string')
            ->addColumn('name', 'string')
            ->addColumn('accounts_write', 'boolean')
            ->addColumn('accounts_restrict', 'boolean')
            ->addColumn('settings_edit', 'boolean')
            ->addColumn('catalog_edit', 'boolean')
            ->addColumn('business_admin', 'boolean')
            ->addColumn('business_billing', 'boolean')
            ->addColumn('customers_create', 'boolean')
            ->addColumn('customers_edit', 'boolean')
            ->addColumn('customers_delete', 'boolean')
            ->addColumn('invoices_create', 'boolean')
            ->addColumn('invoices_issue', 'boolean')
            ->addColumn('invoices_edit', 'boolean')
            ->addColumn('invoices_delete', 'boolean')
            ->addColumn('credit_notes_create', 'boolean')
            ->addColumn('credit_notes_issue', 'boolean')
            ->addColumn('credit_notes_edit', 'boolean')
            ->addColumn('credit_notes_delete', 'boolean')
            ->addColumn('estimates_create', 'boolean')
            ->addColumn('estimates_issue', 'boolean')
            ->addColumn('estimates_edit', 'boolean')
            ->addColumn('estimates_delete', 'boolean')
            ->addColumn('emails_send', 'boolean')
            ->addColumn('text_messages_send', 'boolean')
            ->addColumn('letters_send', 'boolean')
            ->addColumn('payments_create', 'boolean')
            ->addColumn('payments_edit', 'boolean')
            ->addColumn('payments_delete', 'boolean')
            ->addColumn('charges_create', 'boolean')
            ->addColumn('refunds_create', 'boolean')
            ->addColumn('credits_create', 'boolean')
            ->addColumn('credits_apply', 'boolean')
            ->addColumn('reports_create', 'boolean')
            ->addColumn('imports_create', 'boolean')
            ->addTimestamps()
            ->create();
    }
}
