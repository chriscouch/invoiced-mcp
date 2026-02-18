<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CanceledCompany extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CanceledCompanies');
        $table->addColumn('name', 'string')
            ->addColumn('email', 'string')
            ->addColumn('username', 'string')
            ->addColumn('custom_domain', 'string', ['null' => true, 'default' => null])
            ->addColumn('type', 'enum', ['values' => ['company', 'person']])
            ->addColumn('address1', 'string', ['null' => true, 'default' => null, 'length' => 1000])
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string', ['null' => true, 'default' => null])
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->addColumn('tax_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('address_extra', 'text', ['null' => true, 'default' => null])
            ->addColumn('creator_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('plan', 'string', ['length' => 50])
            ->addColumn('stripe_customer', 'string', ['null' => true, 'default' => null])
            ->addColumn('not_charged', 'boolean')
            ->addColumn('past_due', 'boolean')
            ->addColumn('canceled_at', 'integer', ['null' => true, 'default' => null])
            ->addColumn('trial_started', 'integer', ['null' => true, 'default' => null])
            ->addColumn('converted_at', 'integer', ['null' => true, 'default' => null])
            ->addColumn('converted_from', 'string')
            ->addColumn('canceled_reason', 'string')
            ->addColumn('referred_by', 'string', ['null' => true, 'default' => null])
            ->addColumn('pipedrive_deal_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('username')
            ->addIndex('email')
            ->addIndex('stripe_customer')
            ->create();
    }
}
