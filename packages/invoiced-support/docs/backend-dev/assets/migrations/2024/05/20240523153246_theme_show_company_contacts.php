<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ThemeShowCompanyContacts extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Themes')
            ->addColumn('show_company_phone', 'boolean', ['default' => true])
            ->addColumn('show_company_email', 'boolean', ['default' => true])
            ->addColumn('show_company_website', 'boolean', ['default' => true])
            ->update();
    }
}
