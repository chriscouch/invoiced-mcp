<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LongerGlAccountCodes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('GlAccounts')
            ->changeColumn('code', 'string', ['collation' => 'utf8_bin'])
            ->update();

        $this->table('CatalogItems')
            ->changeColumn('gl_account', 'string', ['collation' => 'utf8_bin', 'null' => true, 'default' => null])
            ->update();
    }
}
