<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApprovalPolishing extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Tasks')->changeColumn('action', 'string', ['length' => 255])->update();
        $this->table('VendorCredits')->removeColumn('approval_status')->update();
        $this->table('Bills')->removeColumn('approval_status')->update();
        $this->ensureInstantEnd();
    }
}
