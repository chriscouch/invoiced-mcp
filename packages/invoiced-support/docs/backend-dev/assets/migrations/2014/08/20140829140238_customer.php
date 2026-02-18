<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Customer extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Customers');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->addColumn('type', 'enum', ['values' => ['company', 'person']])
            ->addColumn('number', 'string', ['length' => 32])
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('collection_mode', 'enum', ['values' => ['auto', 'manual']])
            ->addColumn('autopay', 'boolean')
            ->addColumn('payment_terms', 'string', ['null' => true, 'default' => null])
            ->addColumn('attention_to', 'string', ['null' => true, 'default' => null])
            ->addColumn('address1', 'string', ['null' => true, 'default' => null, 'length' => 1000])
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string', ['null' => true, 'default' => null])
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->addColumn('language', 'string', ['null' => true, 'default' => null, 'length' => 2])
            ->addColumn('tax_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('phone', 'string', ['null' => true, 'default' => null])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('default_source_type', 'enum', ['values' => ['card', 'bank_account', 'sepa_account'], 'null' => true, 'default' => null])
            ->addColumn('default_source_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('sign_up_page_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('parent_customer', 'integer', ['null' => true, 'default' => null])
            ->addColumn('taxable', 'boolean', ['default' => true])
            ->addColumn('taxes', 'string', ['default' => '[]'])
            ->addColumn('chase', 'boolean', ['default' => true])
            ->addColumn('consolidated', 'boolean')
            ->addColumn('credit_limit', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('credit_hold', 'boolean')
            ->addColumn('credit_balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addTimestamps()
            ->addIndex('name')
            ->addIndex('number')
            ->addIndex('autopay')
            ->addIndex('parent_customer')
            ->addIndex('client_id', ['unique' => true])
            ->addIndex('client_id_exp')
            ->addIndex('updated_at')
            ->create();
    }
}
