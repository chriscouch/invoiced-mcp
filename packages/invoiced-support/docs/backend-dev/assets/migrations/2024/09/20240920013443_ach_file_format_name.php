<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AchFileFormatName extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AchFileFormats')
            ->addIndex(['tenant_id', 'name'], ['unique' => true])
            ->update();
    }
}
