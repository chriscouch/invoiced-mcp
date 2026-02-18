<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddUnappliedPaymentsToAttachment extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Attachments')
            ->changeColumn('parent_type', 'enum', ['values' => ['comment', 'credit_note', 'estimate', 'invoice', 'unapplied_payment']])
            ->update();
    }
}
