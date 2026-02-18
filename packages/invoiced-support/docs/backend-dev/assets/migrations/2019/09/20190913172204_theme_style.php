<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ThemeStyle extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Themes')
            ->addColumn('style', 'string', ['length' => 20, 'default' => 'classic'])
            ->update();
    }
}
