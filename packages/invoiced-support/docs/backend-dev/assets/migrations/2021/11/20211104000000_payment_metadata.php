<?php

use App\Core\Multitenant\MultitenantModelMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class PaymentMetadata extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PaymentAttributes');
        $this->addTenant($table);
        $table->addColumn('name', 'string', ['length' => 40])
            ->addColumn('type', 'integer', ['limit' => MysqlAdapter::INT_TINY])
            ->addIndex(['tenant_id', 'name'], ['unique' => true, 'name' => 'tenant_name'])
            ->create();

        $table = $this->table('PaymentIntegerValues', ['id' => false, 'primary_key' => ['object_id', 'attribute_id']]);
        $table->addColumn('object_id', 'integer')
            ->addColumn('attribute_id', 'integer')
            ->addColumn('value', 'integer')
            ->addForeignKey('object_id', 'Payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_id', 'PaymentAttributes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['attribute_id', 'value'], ['name' => 'attribute_value'])
            ->create();

        $table = $this->table('PaymentStringValues', ['id' => false, 'primary_key' => ['object_id', 'attribute_id']]);
        $table->addColumn('object_id', 'integer')
            ->addColumn('attribute_id', 'integer')
            ->addColumn('value', 'string')
            ->addForeignKey('object_id', 'Payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_id', 'PaymentAttributes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['attribute_id', 'value'], ['name' => 'attribute_value'])
            ->create();

        $table = $this->table('PaymentDecimalValues', ['id' => false, 'primary_key' => ['object_id', 'attribute_id']]);
        $table->addColumn('object_id', 'integer')
            ->addColumn('attribute_id', 'integer')
            ->addColumn('value', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addForeignKey('object_id', 'Payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_id', 'PaymentAttributes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['attribute_id', 'value'], ['name' => 'attribute_value'])
            ->create();

        $table = $this->table('PaymentMoneyValues', ['id' => false, 'primary_key' => ['object_id', 'attribute_id']]);
        $table->addColumn('object_id', 'integer')
            ->addColumn('attribute_id', 'integer')
            ->addColumn('value', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addForeignKey('object_id', 'Payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_id', 'PaymentAttributes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex(['attribute_id', 'value'], ['name' => 'attribute_value'])
            ->create();

        $this->table('CustomFields')
            ->changeColumn('object', 'enum', ['null' => true, 'default' => null, 'values' => ['customer', 'invoice', 'credit_note', 'estimate', 'line_item', 'subscription', 'transaction', 'plan', 'item', 'payment']])
            ->update();
    }
}
