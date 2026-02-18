<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PublishableKey extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PublishableKeys');
        $this->addTenant($table);
        $table
            ->addColumn('secret', 'text')
            ->addIndex(['secret'], ['unique' => true])
            ->addTimestamps()
            ->create();

        $this->execute("INSERT IGNORE INTO PublishableKeys (tenant_id, secret) SELECT id, CONCAT(country, SUBSTRING(MD5(RAND(id)), 1, 30)) FROM Companies");


        $table = $this->table('TokenizationApplications');
        $this->addTenant($table);
        $table
            ->addColumn('identifier', 'string', ['length' => 28])
            ->addColumn('type', 'integer')
            ->addColumn('funding', 'string', ['null' => true, 'default' => null, 'length' => 30])
            ->addColumn('brand', 'string', ['null' => true, 'default' => null, 'length' => 30])
            ->addColumn('last4', 'string', ['length' => 4])
            ->addColumn('exp_month', 'integer', ['null' => true, 'default' => null, 'length' => 2])
            ->addColumn('exp_year', 'integer', ['null' => true, 'default' => null, 'length' => 4])
            ->addColumn('gateway_customer', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('gateway', 'string')
            ->addColumn('gateway_id', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('merchant_account_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('failure_reason', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['null' => true, 'default' => null, 'length' => 2])
            ->addColumn('bank_name', 'string', ['default' => null, 'null' => true])
            ->addColumn('routing_number', 'string', ['default' => null, 'null' => true])
            ->addColumn('account_holder_name', 'string', ['default' => null, 'null' => true])
            ->addColumn('account_holder_type', 'string', ['default' => null, 'null' => true])
            ->addColumn('account_type', 'string', ['default' => null, 'null' => true])
            ->addTimestamps()
            ->addIndex(['identifier'], ['unique' => true])
            ->create();
    }
}
