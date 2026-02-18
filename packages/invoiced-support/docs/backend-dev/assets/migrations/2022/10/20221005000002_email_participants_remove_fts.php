<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailParticipantsRemoveFts extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('EmailParticipants')
            ->removeIndex(['email_address', 'name'])
            ->update();
    }
}
