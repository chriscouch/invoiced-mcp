<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ContactsIndexImprovement extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Contacts')
            ->addIndex(['email'])
            ->addIndex(['tenant_id', 'sms_enabled'])
            ->removeIndex(['sms_enabled'])
            ->update();
    }
}
