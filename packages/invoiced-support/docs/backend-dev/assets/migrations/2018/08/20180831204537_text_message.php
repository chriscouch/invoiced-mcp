<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TextMessage extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('TextMessages', ['id' => false, 'primary_key' => ['id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string')
            ->addColumn('state', 'string')
            ->addColumn('to', 'string')
            ->addColumn('message', 'text')
            ->addColumn('twilio_id', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->save();
    }
}
