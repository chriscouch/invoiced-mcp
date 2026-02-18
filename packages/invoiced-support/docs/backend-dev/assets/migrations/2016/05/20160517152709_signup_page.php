<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SignupPage extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SignUpPages');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('type', 'enum', ['default' => 'recurring', 'values' => ['recurring', 'autopay']])
            ->addColumn('billing_address', 'boolean')
            ->addColumn('shipping_address', 'boolean')
            ->addColumn('plans', 'text')
            ->addColumn('taxes', 'string', ['default' => '[]'])
            ->addColumn('trial_period_days', 'integer')
            ->addColumn('has_quantity', 'boolean')
            ->addColumn('has_coupon_code', 'boolean')
            ->addColumn('snap_to_nth_day', 'integer', ['null' => true, 'default' => null])
            ->addColumn('allow_multiple_subscriptions', 'boolean')
            ->addColumn('setup_fee', 'string', ['null' => true, 'default' => null])
            ->addColumn('custom_fields', 'text')
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('header_text', 'text', ['default' => null, 'null' => true])
            ->addColumn('tos_url', 'string', ['default' => null, 'null' => true])
            ->addColumn('thanks_url', 'text', ['default' => null, 'null' => true])
            ->addTimestamps()
            ->addIndex('client_id', ['unique' => true])
            ->addIndex('client_id_exp')
            ->create();

        $this->table('Customers')
            ->addForeignKey('sign_up_page_id', 'SignUpPages', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
