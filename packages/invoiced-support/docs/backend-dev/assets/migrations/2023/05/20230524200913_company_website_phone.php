<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CompanyWebsitePhone extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Companies')
            ->addColumn('website', 'string', ['null' => true, 'default' => null])
            ->addColumn('phone', 'string', ['null' => true, 'default' => null, 'length' => 25])
            ->update();
    }
}
