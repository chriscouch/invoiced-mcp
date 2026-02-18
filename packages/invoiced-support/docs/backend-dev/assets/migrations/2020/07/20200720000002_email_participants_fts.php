<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailParticipantsFts extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('EmailParticipants')
            ->removeIndex('tenant_id')
            ->removeIndex('email_address')
            ->addIndex(['email_address', 'name'], ['type' => 'fulltext'])
            ->addIndex(['tenant_id', 'email_address'], ['unique' => true])
            ->update();
    }
}
