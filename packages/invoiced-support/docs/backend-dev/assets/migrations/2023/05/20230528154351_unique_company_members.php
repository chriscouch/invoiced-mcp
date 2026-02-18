<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueCompanyMembers extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Members')
            ->addIndex(['user_id', 'tenant_id'], ['unique' => true])
            ->update();
        if ($this->table('Members')->hasIndex('user_id')) {
            $this->table('Members')
                ->removeIndex('user_id')
                ->update();
        }
    }
}
