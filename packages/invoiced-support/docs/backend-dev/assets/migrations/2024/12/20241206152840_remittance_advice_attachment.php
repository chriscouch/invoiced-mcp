<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemittanceAdviceAttachment extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Attachments')
            ->changeColumn('parent_type', 'enum', ['values' => ['comment', 'credit_note', 'estimate', 'invoice', 'payment', 'email', 'customer', 'remittance_advice']])
            ->update();
    }
}
