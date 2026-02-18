<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SmsTemplate extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SmsTemplates');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('message', 'text')
            ->addColumn('language', 'string', ['default' => 'en', 'length' => 2])
            ->addTimestamps()
            ->create();

        $this->table('ChasingCadenceSteps')
            ->addColumn('sms_template_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('sms_template_id', 'SmsTemplates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
